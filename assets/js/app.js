// app.js
require('dotenv').config();
const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const { sequelize } = require('./models'); // Import sequelize instance
const postController = require('./controllers/post.controller');

const app = express();
app.use(express.json()); // For parsing application/json
app.use(express.urlencoded({ extended: true })); // For parsing application/x-www-form-urlencoded

// Create uploads directory if it doesn't exist
const uploadsDir = path.join(__dirname, 'uploads');
if (!fs.existsSync(uploadsDir)) {
    fs.mkdirSync(uploadsDir);
}

// Multer Storage Configuration
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        cb(null, uploadsDir);
    },
    filename: (req, file, cb) => {
        cb(null, Date.now() + '-' + file.originalname);
    }
});

// Multer File Filter for security
const fileFilter = (req, file, cb) => {
    const allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/quicktime'];
    if (allowedMimes.includes(file.mimetype)) {
        cb(null, true);
    } else {
        cb(new Error('Invalid file type. Only images (jpeg, png, gif) and videos (mp4, mov) are allowed.'), false);
    }
};

// Multer Upload Middleware
const upload = multer({ 
    storage: storage,
    limits: { fileSize: 100 * 1024 * 1024 }, // 100MB file size limit
    fileFilter: fileFilter
});

// Routes
app.post('/api/posts', upload.single('media'), postController.createPost);

// Database synchronization and server start
const PORT = process.env.PORT || 3001;
sequelize.sync().then(() => {
    app.listen(PORT, () => console.log(`Moderation Node Server running on port ${PORT}`));
}).catch(err => {
    console.error('Unable to connect to the database:', err);
});