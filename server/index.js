const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const mysql = require('mysql2/promise');
const cors = require('cors');
require('dotenv').config();

const app = express();
app.use(cors());
const server = http.createServer(app);

const isProduction = process.env.NODE_ENV === 'production';
const allowedOrigin = process.env.ALLOWED_ORIGIN || "*";

const io = new Server(server, {
    cors: {
        origin: isProduction ? allowedOrigin : "*",
        methods: ["GET", "POST"]
    },
    pingTimeout: 30000,
    pingInterval: 10000
});

// Database connection pool
const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASS,
    database: process.env.DB_NAME,
    waitForConnections: true,
    connectionLimit: isProduction ? 20 : 10,
    queueLimit: 0
});

// Map to track which player is in which room for easy cleanup
const playerToSocket = new Map();

io.on('connection', (socket) => {
    console.log('User connected:', socket.id);

    socket.on('join_room', async ({ token, roomCode }) => {
        try {
            // Validate player token
            const [players] = await pool.execute(
                'SELECT id, room_id, username FROM players WHERE session_token = ?',
                [token]
            );

            if (players.length === 0) {
                socket.emit('error', 'Invalid Session');
                return;
            }

            const player = players[0];
            const roomId = player.room_id;

            // Leave previous room if any
            if (socket.roomId) socket.leave(`room_${socket.roomId}`);

            socket.join(`room_${roomId}`);
            socket.roomId = roomId;
            socket.playerId = player.id;
            socket.username = player.username;
            playerToSocket.set(player.id, socket.id);

            console.log(`Player ${player.username} joined room ${roomId}`);

            // Notify others
            socket.to(`room_${roomId}`).emit('player_joined', {
                playerId: player.id,
                username: player.username
            });

        } catch (err) {
            console.error('Join Room Error:', err);
            socket.emit('error', 'Internal Server Error');
        }
    });

    socket.on('draw_stroke', (stroke) => {
        if (!socket.roomId) return;
        // Broadcast to everyone else in the room
        socket.to(`room_${socket.roomId}`).emit('draw_stroke', stroke);
    });

    socket.on('clear_canvas', () => {
        if (!socket.roomId) return;
        socket.to(`room_${socket.roomId}`).emit('clear_canvas');
        // Optional: Save to DB or keep purely transient if managed by syncState
    });

    socket.on('undo', () => {
        if (!socket.roomId) return;
        socket.to(`room_${socket.roomId}`).emit('undo');
    });

    socket.on('reaction', (emoji) => {
        if (!socket.roomId) return;
        socket.to(`room_${socket.roomId}`).emit('reaction', emoji);

        // Also fire as a transient message for chat log if desired
        // io.in(`room_${socket.roomId}`).emit('new_message', { ... });
    });

    socket.on('send_message', async ({ message, type }) => {
        if (!socket.roomId || !socket.playerId) return;

        try {
            let msgType = type || 'chat';
            let processedMessage = message;

            // Guess Checking Logic
            if (msgType === 'chat') {
                const [rounds] = await pool.execute(
                    `SELECT r.id, r.word_id, w.word 
                     FROM rounds r 
                     JOIN words w ON r.word_id = w.id 
                     WHERE r.room_id = ? AND r.status = 'drawing' 
                     ORDER BY r.id DESC LIMIT 1`,
                    [socket.roomId]
                );

                if (rounds.length > 0) {
                    const currentWord = rounds[0].word.toLowerCase().trim();
                    const guess = message.toLowerCase().trim();

                    if (guess === currentWord) {
                        // Correct Guess!
                        msgType = 'guess';
                        processedMessage = `guessed the word!`;

                        // Update Score in DB
                        await pool.execute(
                            'UPDATE players SET score = score + 10 WHERE id = ?',
                            [socket.playerId]
                        );

                        // Note: We don't change the round status here, 
                        // as PHP state machine will do it on next poll 
                        // or when time is up. But the message will show as a guess.
                    }
                }
            }

            // Relay to all
            io.in(`room_${socket.roomId}`).emit('new_message', {
                id: Date.now(),
                player_id: socket.playerId,
                username: socket.username,
                message: processedMessage,
                type: msgType,
                created_at: new Date()
            });

            await pool.execute(
                'INSERT INTO messages (room_id, player_id, message, type) VALUES (?, ?, ?, ?)',
                [socket.roomId, socket.playerId, processedMessage, msgType]
            );

        } catch (err) {
            console.error('Message Error:', err);
        }
    });

    socket.on('disconnect', () => {
        console.log('User disconnected:', socket.id);
        if (socket.playerId) {
            playerToSocket.delete(socket.playerId);
        }
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`WebSocket Server running on port ${PORT}`);
});
