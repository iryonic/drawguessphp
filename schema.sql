-- Database Schema for DrawGuess

CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(10) NOT NULL UNIQUE,
    host_id INT,
    current_round INT DEFAULT 1,
    max_rounds INT DEFAULT 3,
    round_duration INT DEFAULT 60, -- seconds
    status ENUM('lobby', 'playing', 'finished') DEFAULT 'lobby',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    avatar VARCHAR(255),
    score INT DEFAULT 0,
    is_host BOOLEAN DEFAULT FALSE,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    session_token VARCHAR(64) NOT NULL, -- To reconnect or identify api requests
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    UNIQUE(room_id, username)
);

CREATE TABLE IF NOT EXISTS words (
    id INT AUTO_INCREMENT PRIMARY KEY,
    word VARCHAR(50) NOT NULL UNIQUE,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'easy'
);

CREATE TABLE IF NOT EXISTS rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    round_number INT NOT NULL,
    drawer_id INT,
    word_id INT,
    start_time TIMESTAMP NULL,
    end_time TIMESTAMP NULL,
    status ENUM('choosing', 'drawing', 'ended') DEFAULT 'choosing',
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (drawer_id) REFERENCES players(id) ON DELETE SET NULL,
    FOREIGN KEY (word_id) REFERENCES words(id)
);

CREATE TABLE IF NOT EXISTS strokes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    round_id INT NOT NULL,
    color VARCHAR(20) DEFAULT '#000000',
    size INT DEFAULT 5,
    points TEXT NOT NULL, -- JSON array of {x, y}
    sequence_id INT NOT NULL, -- To order strokes correctly client side
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    round_id INT, -- Can be null if in lobby
    player_id INT, -- Null for system messages
    message VARCHAR(255),
    type ENUM('chat', 'guess', 'system') DEFAULT 'chat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Seed some words
INSERT IGNORE INTO words (word, difficulty) VALUES 
('Cat', 'easy'), ('Sun', 'easy'), ('Apple', 'easy'), ('House', 'easy'), ('Tree', 'easy'),
('Bicycle', 'medium'), ('Guitar', 'medium'), ('Pizza', 'medium'), ('Helicopter', 'medium'),
('Astronaut', 'hard'), ('Sphinx', 'hard'), ('Waterfall', 'hard');
