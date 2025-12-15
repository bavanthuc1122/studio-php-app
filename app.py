from flask import Flask, render_template, request, jsonify
import json
import os
import random
from datetime import datetime

app = Flask(__name__)
DB_FILE = 'database.txt'
LABELS_FILE = 'labels.json'
CONFIG_FILE = 'config.json'
# --- CONFIG ---
# Những Label khách hàng ĐƯỢC PHÉP thấy (Hardcode)
PUBLIC_LABELS = ["Mới", "Đã xử lý", "Chờ phản hồi", "Hoàn thành"] 
# --- HELPER: Load Config ---
def load_config():
    return load_json(CONFIG_FILE, {
        "bg_image": "https://images.unsplash.com/photo-1557683316-973673baf926?q=80&w=2029",
        "text_color": "#ffffff",
        "glass_color": "rgba(255, 255, 255, 0.25)"
    })

def load_json(filename, default_data):
    if not os.path.exists(filename):
        return default_data
    try:
        with open(filename, 'r', encoding='utf-8') as f:
            content = f.read()
            return json.loads(content) if content else default_data
    except:
        return default_data

def save_json(filename, data):
    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

def load_db(): return load_json(DB_FILE, [])
def load_labels(): return load_json(LABELS_FILE, PUBLIC_LABELS) # Mặc định lấy list public

# --- ROUTES ---
@app.route('/')
def client_view():
    # Truyền config sang file HTML
    conf = load_config()
    return render_template('client.html', config=conf)
# --- THÊM API ADMIN ---
@app.route('/api/admin/update_config', methods=['POST'])
def update_config():
    data = request.json
    current_conf = load_config()
    
    # Cập nhật các trường mới
    if 'bg_image' in data: current_conf['bg_image'] = data['bg_image']
    if 'text_color' in data: current_conf['text_color'] = data['text_color']
    
    save_json(CONFIG_FILE, current_conf)
    return jsonify({'success': True, 'message': 'Đã cập nhật giao diện!'})

@app.route('/admin')
def admin_view(): return render_template('admin.html')

# --- API CLIENT ---
@app.route('/api/submit', methods=['POST'])
def submit_info():
    data = request.json
    db = load_db()
    
    if any(item['image_link'] == data['image_link'] for item in db):
        return jsonify({'success': False, 'message': 'Link ảnh đã tồn tại!'}), 400

    # Random Avatar (1.png -> 5.png)
    avatar_id = random.randint(1, 5) 

    new_record = {
        'id': len(db) + 1,
        'customer_name': data['customer_name'],
        'shoot_date': data['shoot_date'],
        'image_link': data['image_link'],
        'note': data.get('note', ''),
        'status': 'new',
        'label': 'Mới',
        'avatar': f'/static/avatars/{avatar_id}.png', # Đường dẫn avatar
        'result_link': '',    # Link trả ảnh (khi hoàn thành)
        'result_content': '', # Lời nhắn (khi hoàn thành)
        'created_at': datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    }
    
    db.append(new_record)
    save_json(DB_FILE, db)
    return jsonify({'success': True, 'message': 'Đã gửi thông tin!'})

@app.route('/api/check', methods=['POST'])
def check_info():
    link = request.json.get('image_link')
    db = load_db()
    record = next((item for item in db if item['image_link'] == link), None)
    
    if record:
        # LOGIC ẨN LABEL NỘI BỘ
        display_record = record.copy()
        if display_record['label'] not in PUBLIC_LABELS:
            display_record['label'] = "Đang xử lý" # Khách chỉ thấy dòng này nếu Admin dùng label lạ
            
        return jsonify({'success': True, 'data': display_record})
    return jsonify({'success': False, 'message': 'Không tìm thấy thông tin.'}), 404

# --- API ADMIN ---
@app.route('/api/login', methods=['POST'])
def login():
    data = request.json
    if data['username'] == 'admin' and data['password'] == 'studio123':
        return jsonify({'success': True})
    return jsonify({'success': False}), 401

@app.route('/api/admin/data', methods=['GET'])
def get_admin_data():
    return jsonify({'messages': load_db(), 'labels': load_labels()})

@app.route('/api/admin/manage_label', methods=['POST'])
def manage_label():
    action = request.json.get('action') # 'add' or 'delete'
    label_name = request.json.get('label')
    current_labels = load_labels()

    if action == 'add':
        if label_name not in current_labels:
            current_labels.append(label_name)
    elif action == 'delete':
        if label_name in current_labels and label_name not in PUBLIC_LABELS: # Không cho xóa Label cứng
            current_labels.remove(label_name)
        else:
            return jsonify({'success': False, 'message': 'Không thể xóa Label mặc định!'})

    save_json(LABELS_FILE, current_labels)
    return jsonify({'success': True, 'labels': current_labels})

@app.route('/api/admin/update_ticket', methods=['POST'])
def update_ticket():
    data = request.json
    db = load_db()
    found = False
    
    for item in db:
        if item['image_link'] == data['image_link']:
            if 'customer_name' in data: item['customer_name'] = data['customer_name']
            if 'note' in data: item['note'] = data['note']
            if 'label' in data: item['label'] = data['label']
            
            # Cập nhật kết quả trả về (nếu có)
            if 'result_link' in data: item['result_link'] = data['result_link']
            if 'result_content' in data: item['result_content'] = data['result_content']
            
            found = True
            break
            
    if found:
        save_json(DB_FILE, db)
        return jsonify({'success': True})
    return jsonify({'success': False}), 404


@app.route('/api/admin/delete_ticket', methods=['POST'])
def delete_ticket():
    link_to_delete = request.json.get('image_link')
    db = load_db()
    
    # Giữ lại những item KHÔNG trùng link (tức là xóa item trùng link)
    new_db = [item for item in db if item['image_link'] != link_to_delete]
    
    if len(new_db) < len(db): # Nếu độ dài danh sách giảm -> Đã xóa được
        save_json(DB_FILE, new_db)
        return jsonify({'success': True, 'message': 'Đã xóa thành công!'})
    
    return jsonify({'success': False, 'message': 'Không tìm thấy dữ liệu để xóa'}), 404

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)