// models/index.js
const Sequelize = require('sequelize');
const sequelize = require('../config/database');

const User = require('./user.model')(sequelize, Sequelize);
const Post = require('./post.model')(sequelize, Sequelize);
const ModerationLog = require('./moderationlog.model')(sequelize, Sequelize);

// Define associations
User.hasMany(Post, { foreignKey: 'userId', as: 'posts' });
Post.belongsTo(User, { foreignKey: 'userId', as: 'user' });

User.hasMany(ModerationLog, { foreignKey: 'userId', as: 'moderationLogs' });
ModerationLog.belongsTo(User, { foreignKey: 'userId', as: 'user' });

Post.hasMany(ModerationLog, { foreignKey: 'postId', as: 'moderationLogs' });
ModerationLog.belongsTo(Post, { foreignKey: 'postId', as: 'post' });

const db = {
    sequelize,
    Sequelize,
    User,
    Post,
    ModerationLog
};

// Sync all models with the database
// db.sequelize.sync({ force: false }); // Use { force: true } only for development to drop and recreate tables
module.exports = db;