-- Migration: Interaction (Discussion/Chat) Section

CREATE TABLE IF NOT EXISTS interaction_topics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS interaction_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    topic_id INT NOT NULL,
    user_id INT,
    banner_img VARCHAR(255),
    message_type ENUM('text', 'audio') NOT NULL,
    message_text TEXT,
    audio_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (topic_id) REFERENCES interaction_topics(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
