const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const cors = require("cors");

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    cors: { origin: "*", methods: ["GET", "POST"] },
});

app.use(cors({ origin: "*", methods: ["GET", "POST"] }));
app.use(express.json());

const PORT = process.env.PORT || 5009;

// Lưu trữ trạng thái người dùng online
const onlineUsers = new Map(); // Key: userId, Value: socket.id

io.on("connection", (socket) => {
    console.log(`User connected: ${socket.id}`);

    socket.on("joinRoom", (chatRoomId) => {
        socket.join(chatRoomId);
        console.log(`User ${socket.id} joined room: ${chatRoomId}`);
    });

    socket.on("userOnline", (userId) => {
        onlineUsers.set(userId, socket.id); // Lưu trạng thái online
        console.log(`User ${userId} is online`);
        io.emit("onlineStatusUpdate", { userId, status: "online" });
    });

    socket.on("checkUserStatus", (userId) => {
        const status = onlineUsers.has(userId) ? "online" : "offline";
        socket.emit("userStatusResponse", { userId, status });
    });

    // Xử lý gửi tin nhắn real-time
    socket.on("sendMessage", (messageData) => {
        const { chatRoomId, userId, message } = messageData;
        if (!chatRoomId || !userId || !message) {
            socket.emit("messageError", { error: "Missing required fields" });
            return;
        }
        const formattedMessage = {
            chatRoomId,
            userId,
            message,
            timestamp: new Date().toISOString(),
        };
        io.to(chatRoomId).emit("newMessage", formattedMessage);
        console.log(
            `Message sent to room ${chatRoomId} by ${userId}: ${message}`
        );
    });

    socket.on("disconnect", () => {
        let disconnectedUserId = null;
        for (const [userId, socketId] of onlineUsers.entries()) {
            if (socketId === socket.id) {
                disconnectedUserId = userId;
                onlineUsers.delete(userId);
                break;
            }
        }
        if (disconnectedUserId) {
            console.log(`User ${disconnectedUserId} disconnected`);
            io.emit("onlineStatusUpdate", {
                userId: disconnectedUserId,
                status: "offline",
            });
        }
    });

    socket.on("send-notify-request-connection", (userId) => {
        console.log("Reveived request connection from user: " + userId);
        io.to(onlineUsers.get(userId)).emit(
            "receive-notify-request-connection"
        );
    });

    socket.on("cancel-notify-request-connection", (userId) => {
        console.log("Reveived cancel request connection from user: " + userId);
        io.to(onlineUsers.get(userId)).emit(
            "receive-cancel-notify-request-connection"
        );
    });

    socket.on("reject-notify-request-connection", (userId) => {
        console.log("Reveived reject request connection from user: " + userId);
        io.to(onlineUsers.get(userId)).emit(
            "receive-reject-notify-request-connection"
        );
    });

    socket.on("accept-notify-request-connection", (userId) => {
        console.log("Reveived accept request connection from user: " + userId);
        io.to(onlineUsers.get(userId)).emit(
            "receive-accept-notify-request-connection"
        );
    });
});

app.post("/broadcast", (req, res) => {
    const messageData = req.body;
    if (!messageData.chatRoomId) {
        return res
            .status(400)
            .json({ status: "error", message: "Missing chatRoomId" });
    }

    io.to(messageData.chatRoomId).emit("newMessage", messageData);
    res.status(200).json({ status: "success", message: "Message broadcasted" });
});

server.listen(PORT, () => {
    console.log(`Socket.IO server running on http://localhost:${PORT}`);
});
// const express = require("express");
// const http = require("http");
// const { Server } = require("socket.io");
// const cors = require("cors");

// const app = express();
// const server = http.createServer(app);

// const PORT = process.envPORT || 5009; // Port cho Socket.IO server

// // Cấu hình CORS cho Express (để nhận request từ Laravel)
// // và cho Socket.IO (để nhận kết nối từ React)
// app.use(
//   cors({
//     origin: "*", // Cho phép tất cả origin (có thể giới hạn trong production)
//     methods: ["GET", "POST"],
//   })
// );

// // Middleware để parse JSON body từ request của Laravel
// app.use(express.json());

// // Cấu hình Socket.IO Server
// const io = new Server(server, {
//   cors: {
//     origin: "*", // Chỉ cho phép kết nối từ React app (có thể giới hạn trong production)
//     methods: ["GET", "POST"],
//   },
// });

// console.log(`Socket.IO server is setting up on port ${PORT}`);

// // Lắng nghe kết nối từ client React
// io.on("connection", (socket) => {
//   console.log(`⚡: User connected: ${socket.id}`);

//   // Khi client tham gia một phòng chat
//   socket.on("joinRoom", (chatRoomId) => {
//     socket.join(chatRoomId); // Tham gia phòng với chatRoomId
//     console.log(`User ${socket.id} joined room: ${chatRoomId}`);
//   });

//   socket.on("userOnline", (userId) => {
//     // Lưu trạng thái user online (ví dụ: trong một object hoặc database)
//     console.log(`User ${userId} is online`);
//     io.emit("onlineStatusUpdate", { userId, status: "online" });
//   });

//   socket.on("checkUserStatus", (userId) => {
//     // Kiểm tra trạng thái user (ví dụ: từ object hoặc database)
//     const status = "online"; // Giả lập, thay bằng logic thực tế
//     socket.emit("userStatusResponse", { userId, status });
//   });

//   // Khi client rời phòng chat (tùy chọn)
//   socket.on("leaveRoom", (chatRoomId) => {
//     socket.leave(chatRoomId);
//     console.log(`User ${socket.id} left room: ${chatRoomId}`);
//   });

//   // Lắng nghe khi client ngắt kết nối
//   socket.on("disconnect", () => {
//     console.log(`🔥: User disconnected: ${socket.id}`);
//   });
// });

// // Endpoint để nhận yêu cầu broadcast từ Laravel
// app.post("/broadcast", (req, res) => {
//   const messageData = req.body; // Dữ liệu gửi từ Laravel
//   console.log("Received message to broadcast:", messageData);

//   // Kiểm tra dữ liệu đầu vào
//   if (
//     !messageData ||
//     !messageData.chatRoomId ||
//     !messageData.username ||
//     !messageData.message
//   ) {
//     return res.status(400).json({
//       status: "error",
//       message: "Missing chatRoomId, username, or message",
//     });
//   }

//   // Gửi tin nhắn chỉ đến các client trong phòng chat cụ thể
//   io.to(messageData.chatRoomId).emit("newMessage", messageData);

//   res.status(200).json({ status: "success", message: "Message broadcasted" });
// });

// // Route cơ bản để kiểm tra server chạy
// app.get("/", (req, res) => {
//   res.send("Socket.IO server is running.");
// });

// server.listen(PORT, () => {
//   console.log(
//     `🚀 Socket.IO server is running and listening on http://localhost:${PORT}`
//   );
// });
