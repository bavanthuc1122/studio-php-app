# Hướng dẫn đẩy code lên GitHub Repository

Repository đã được cấu hình với remote origin: `https://github.com/bavanthuc1122/studio-php-app.git`

## Trước khi bắt đầu

1. Đảm bảo bạn đã cài đặt Git trên máy tính của mình. Nếu chưa, hãy tải từ: https://git-scm.com/downloads

2. Mở Command Prompt hoặc PowerShell với quyền Administrator (nếu cần thiết)

## Phương pháp 1: Sử dụng HTTPS (Khuyến nghị nếu chưa thiết lập SSH)

1. Mở Command Prompt hoặc PowerShell
2. Di chuyển đến thư mục project:
   ```cmd
   cd "D:\2025\THÁNG 12 Huy Le\webai"
   ```

3. Thêm tất cả các file vào staging area:
   ```cmd
   git add .
   ```

4. Commit các thay đổi:
   ```cmd
   git commit -m "Initial commit with full application"
   ```

5. Push lên GitHub:
   ```cmd
   git push -u origin main
   ```

Khi được yêu cầu, nhập:
- Username: `bavanthuc1122`
- Password: Token truy cập cá nhân của bạn (không phải mật khẩu GitHub)

**Lưu ý:** Nếu bạn chưa tạo Personal Access Token, hãy làm theo hướng dẫn dưới đây.

## Phương pháp 2: Sử dụng SSH (Nếu bạn đã thiết lập SSH keys)

1. Mở Command Prompt hoặc PowerShell
2. Di chuyển đến thư mục project:
   ```cmd
   cd "D:\2025\THÁNG 12 Huy Le\webai"
   ```

3. Thay đổi remote URL sang SSH:
   ```cmd
   git remote set-url origin git@github.com:bavanthuc1122/studio-php-app.git
   ```

4. Thêm tất cả các file vào staging area:
   ```cmd
   git add .
   ```

5. Commit các thay đổi:
   ```cmd
   git commit -m "Initial commit with full application"
   ```

6. Push lên GitHub:
   ```cmd
   git push -u origin main
   ```

## Tạo Personal Access Token (Nếu sử dụng HTTPS)

1. Truy cập https://github.com/settings/tokens
2. Click "Generate new token"
3. Chọn "Fine-grained tokens" hoặc "Classic token" (Classic token đơn giản hơn)
4. Nếu chọn Classic token:
   - Đặt tên token (ví dụ: "Studio App Upload")
   - Chọn phạm vi (scopes): `repo` (đầy đủ quyền cho repositories)
   - Click "Generate token"
5. Sao chép token được tạo ra và lưu lại ở nơi an toàn

## Khắc phục sự cố

### Nếu gặp lỗi "failed to push some refs"

```cmd
git pull origin main --allow-unrelated-histories
git push -u origin main
```

### Nếu gặp lỗi xác thực

1. Xóa credential cache:
   ```cmd
   git config --global --unset credential.helper
   ```

2. Thử push lại và nhập token mới

### Nếu muốn kiểm tra remote URL

```cmd
git remote -v
```

## Nội dung repository sẽ bao gồm

Sau khi push thành công, repository của bạn sẽ có cấu trúc như sau:

```
studio-php-app/
├── static/
│   ├── avatars/
│   │   ├── 1.png
│   │   ├── 2.png
│   │   ├── 3.png
│   │   ├── 4.png
│   │   └── 5.png
│   └── style.css
├── templates/
│   └── client.html
├── .buildpacks
├── admin.php
├── api.php
├── composer.json
├── db_config.php
├── index.php
├── Procfile
├── README.md
├── schema.sql
└── UPLOAD_INSTRUCTIONS.md
```

## Ghi chú quan trọng

1. Đảm bảo rằng tất cả các file đã được thêm vào commit trước khi push
2. File [composer.json](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/composer.json) rất quan trọng để Railway biết cần cài đặt các extension PHP nào
3. File [Procfile](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/Procfile) sử dụng `vendor/bin/heroku-php-apache2` để chạy ứng dụng trên Railway
4. File [.buildpacks](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/.buildpacks) đảm bảo Railway sử dụng đúng PHP buildpack
5. File [db_config.php](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/db_config.php) đã được cập nhật để tự động đọc thông tin kết nối từ biến môi trường Railway