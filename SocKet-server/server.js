// socket-server/server.js
const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const cors = require("cors");

const app = express();
const server = http.createServer(app);

const PORT = process.env.PORT || 5009; // Port cho Socket.IO server

// Cấu hình CORS cho Express (để nhận request từ Laravel)
// và cho Socket.IO (để nhận kết nối từ React)
app.use(
  cors({
    origin: "*", // Cho phép Laravel và React
    methods: ["GET", "POST"]
  })
);

// Middleware để parse JSON body từ request của Laravel
app.use(express.json());

// Cấu hình Socket.IO Server
const io = new Server(server, {
  cors: {
    origin: "*", // Chỉ cho phép kết nối từ React app
    methods: ["GET", "POST"]
  }
});

console.log(`Socket.IO server is setting up on port ${PORT}`);

// Lắng nghe kết nối từ client React
io.on("connection", (socket) => {
  console.log(`⚡: User connected: ${socket.id}`);

  // Lắng nghe khi client ngắt kết nối
  socket.on("disconnect", () => {
    console.log(`🔥: User disconnected: ${socket.id}`);
  });

  // Có thể thêm các event khác ở đây, ví dụ 'typing', 'joinRoom'
});

// Endpoint để nhận yêu cầu broadcast từ Laravel
app.post("/broadcast", (req, res) => {
  const messageData = req.body; // Dữ liệu gửi từ Laravel { username, message, timestamp }
  console.log("Received message to broadcast:", messageData);

  if (!messageData || !messageData.username || !messageData.message) {
    return res
      .status(400)
      .json({ status: "error", message: "Missing username or message" });
  }

  // Phát sự kiện 'newMessage' tới *tất cả* các client đang kết nối
  io.emit("newMessage", messageData);

  res.status(200).json({ status: "success", message: "Message broadcasted" });
});

// Route cơ bản để kiểm tra server chạy
app.get("/", (req, res) => {
  res.send("Socket.IO server is running.");
});

server.listen(PORT, () => {
  console.log(
    `🚀 Socket.IO server is running and listening on http://localhost:${PORT}`
  );
});
