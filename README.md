# Ứng dụng Quản lý Studio Photoshop

## Cấu trúc thư mục

```
.
├── static/
│   └── style.css
├── templates/
│   └── client.html
├── .buildpacks
├── admin.php
├── api.php
├── composer.json
├── db_config.php
├── index.php
├── schema.sql
└── Procfile
```

## Cấu hình Database

Ứng dụng sử dụng MySQL làm cơ sở dữ liệu. File cấu hình [db_config.php](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/db_config.php) đã được cập nhật để tự động đọc thông tin kết nối từ các biến môi trường của Railway:

1. `MYSQL_URL` - URL kết nối MySQL đầy đủ (ưu tiên cao nhất)
2. `DATABASE_URL` - URL kết nối database (dự phòng)
3. Các biến môi trường riêng lẻ:
   - `DB_HOST` - Host của database
   - `DB_PORT` - Port của database
   - `DB_USER` - Tên người dùng
   - `DB_PASS` - Mật khẩu
   - `DB_NAME` - Tên database

## Triển khai trên Railway

1. Tạo một dự án mới trên Railway
2. Kết nối với repository GitHub chứa source code
3. Thêm MySQL service vào dự án
4. Thiết lập các biến môi trường nếu cần (thường Railway tự động cung cấp)
5. Deploy ứng dụng

Railway sẽ tự động sử dụng Heroku PHP buildpack để xây dựng và chạy ứng dụng.

## Endpoint API

- `/api.php?action=login` - Đăng nhập quản trị (POST)
- `/api.php?action=submit` - Gửi thông tin khách hàng (POST)
- `/api.php?action=check` - Kiểm tra thông tin theo link ảnh (POST)
- `/api.php?action=update_client` - Cập nhật thông tin khách hàng (POST)
- `/api.php?action=admin_data` - Lấy dữ liệu cho trang quản trị (GET)
- `/api.php?action=admin_update_ticket` - Cập nhật ticket (POST)
- `/api.php?action=admin_delete_ticket` - Xóa ticket (POST)
- `/api.php?action=admin_manage_label` - Quản lý nhãn (POST)
- `/api.php?action=admin_update_config` - Cập nhật cấu hình giao diện (POST)

## Tài khoản quản trị mặc định

- Tên đăng nhập: `admin`
- Mật khẩu: `studio123`

## Khắc phục sự cố

### Lỗi "could not find driver"

Lỗi này xảy ra khi extension PDO MySQL không được bật trong môi trường PHP. Để khắc phục:

1. Đảm bảo file [composer.json](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/composer.json) có chứa:
   ```json
   {
       "require": {
           "ext-pdo": "*",
           "ext-pdo_mysql": "*"
       }
   }
   ```

2. Railway sẽ tự động cài đặt các extension này khi deploy.

3. Nếu vẫn gặp lỗi, hãy kiểm tra log của ứng dụng trên Railway để xem có thông báo chi tiết hơn.

### Kiểm tra kết nối database

Bạn có thể kiểm tra kết nối database bằng cách truy cập endpoint `/api.php?action=login` với phương thức POST. Nếu kết nối database có vấn đề, bạn sẽ nhận được thông báo lỗi chi tiết.

## Ghi chú phát triển

1. Đã khắc phục lỗi 500 Internal Server Error bằng cách cập nhật file [db_config.php](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/db_config.php) để đọc thông tin kết nối từ biến môi trường Railway
2. Logic xử lý đăng nhập (`action=login`) không còn phụ thuộc vào database
3. Các trang [index.php](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/index.php) và [admin.php](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/admin.php) có cơ chế xử lý lỗi kết nối database riêng biệt để tránh crash toàn ứng dụng
4. Đã thêm file [composer.json](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/composer.json) để yêu cầu các extension cần thiết
5. Đã cấu hình đúng với Railway PHP buildpack bằng cách sử dụng [Procfile](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/Procfile) với `vendor/bin/heroku-php-apache2`
6. Đã thêm file [.buildpacks](file:///d:/2025/TH%C3%81NG%2012%20Huy%20Le/webai/.buildpacks) để đảm bảo Railway sử dụng đúng buildpack