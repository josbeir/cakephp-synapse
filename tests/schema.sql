-- Test database schema for cakephp-synapse

-- Articles table
CREATE TABLE articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    author_id INTEGER,
    published INTEGER DEFAULT 0,
    created DATETIME,
    modified DATETIME
);

-- Users table
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    active INTEGER DEFAULT 1,
    created DATETIME,
    modified DATETIME
);

-- Create indexes
CREATE INDEX idx_articles_author ON articles(author_id);
CREATE INDEX idx_articles_published ON articles(published);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
