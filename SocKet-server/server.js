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

    // Xử lý gọi video
    socket.on("callUser", ({ to, offer }) => {
        console.log(`Incoming call from ${socket.id} to ${to}`);
        io.to(onlineUsers.get(to)).emit("incomingCall", {
            from: socket.id,
            offer,
        });
    });

    socket.on("answerCall", ({ to, answer }) => {
        io.to([...(onlineUsers.get(to) || [])]).emit("callAnswered", {
            answer,
        });
    });

    // Thêm xử lý updateOffer khi có track mới (ví dụ: chia sẻ màn hình)
    socket.on("updateOffer", ({ to, offer }) => {
        const targetSocketIds = onlineUsers.get(to);
        io.to(targetSocketIds).emit("updateOffer", {
            from: socket.id,
            offer,
        });
        console.log(`Update offer sent to user ${to} from ${socket.id}`);
    });

    socket.on("updateAnswer", ({ to, answer }) => {
        const targetSocketIds = onlineUsers.get(to) || [];
        io.to([...targetSocketIds]).emit("updateAnswer", { answer });
        console.log(`Update answer sent to user ${to}`);
    });

    socket.on("iceCandidate", ({ to, candidate }) => {
        io.to([...(onlineUsers.get(to) || [])]).emit("iceCandidate", {
            candidate,
        });
    });

    socket.on("endCall", ({ to }) => {
        console.log(`Call ended from ${socket.id} to ${to}`);

        // Kiểm tra nếu 'to' là socketId đang kết nối
        const targetSocket = io.sockets.sockets.get(to);

        if (targetSocket) {
            // 'to' là socketId hợp lệ
            io.to(to).emit("callEnded");
        } else {
            // 'to' là userId, dùng onlineUsers để lấy danh sách socketId
            const socketIds = onlineUsers.get(to);
            if (socketIds) {
                io.to(socketIds).emit("callEnded");
            }
        }
    });

    // Thêm xử lý dừng chia sẻ màn hình
    socket.on("screenShareEnded", ({ to }) => {
        const targetSocketIds = onlineUsers.get(to);
        io.to(targetSocketIds).emit("screenShareEnded");
        console.log(`Screen share ended sent to user ${to} from ${socket.id}`);
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

    socket.on("send-notify-book-appointment", (receiverId) => {
        io.to(onlineUsers.get(receiverId)).emit(
            "receive-notify-book-appointment"
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
