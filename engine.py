import face_recognition
import sys
import base64
import json
import numpy as np
import cv2

# --- FUNGSI RESIZE & DETEKSI SENSITIF ---
def process_and_find_face(img_cv):
    # 1. Resize gambar jika terlalu HD. Gambar terlalu besar bikin AI HOG "buta".
    h, w = img_cv.shape[:2]
    max_size = 800
    if max(h, w) > max_size:
        scale = max_size / float(max(h, w))
        img_cv = cv2.resize(img_cv, (0,0), fx=scale, fy=scale)
        
    rgb_img = cv2.cvtColor(img_cv, cv2.COLOR_BGR2RGB)
    
    # 2. Cari wajah. number_of_times_to_upsample=2 bikin AI lebih sensitif mencari wajah!
    locations = face_recognition.face_locations(rgb_img, number_of_times_to_upsample=2)
    
    if len(locations) > 0:
        return face_recognition.face_encodings(rgb_img, locations)
    return []

# --- FUNGSI CERDAS PENCARI WAJAH (AUTO-ROTATE) ---
def find_face_encodings(image_path):
    img_cv = cv2.imread(image_path)
    if img_cv is None:
        return []

    # Coba Normal
    encodings = process_and_find_face(img_cv)
    if len(encodings) > 0: return encodings

    # Coba Putar Kanan 90
    img_cw = cv2.rotate(img_cv, cv2.ROTATE_90_CLOCKWISE)
    encodings = process_and_find_face(img_cw)
    if len(encodings) > 0: return encodings

    # Coba Putar Kiri 90
    img_ccw = cv2.rotate(img_cv, cv2.ROTATE_90_COUNTERCLOCKWISE)
    encodings = process_and_find_face(img_ccw)
    if len(encodings) > 0: return encodings

    # Coba Putar Terbalik 180
    img_180 = cv2.rotate(img_cv, cv2.ROTATE_180)
    encodings = process_and_find_face(img_180)
    if len(encodings) > 0: return encodings

    return []

# --- FUNGSI REGISTER ---
def register_face(image_path):
    face_encodings = find_face_encodings(image_path)
    if len(face_encodings) == 0:
        return "TIDAK_ADA_WAJAH"
    
    encoding = face_encodings[0].tolist()
    json_str = json.dumps(encoding)
    b64_string = base64.b64encode(json_str.encode('utf-8')).decode('utf-8')
    return b64_string

# --- FUNGSI LOGIN ---
def match_face(image_path, saved_hash_file_path):
    face_encodings = find_face_encodings(image_path)
    if len(face_encodings) == 0:
        return "TIDAK_ADA_WAJAH"
    
    unknown_encoding = face_encodings[0]
    
    try:
        with open(saved_hash_file_path, 'r') as f:
            saved_b64_string = f.read().strip()
            
        json_str = base64.b64decode(saved_b64_string).decode('utf-8')
        saved_encoding = np.array(json.loads(json_str))
        
        # Toleransi 0.5 cukup imbang untuk keamanan dan kemudahan
        results = face_recognition.compare_faces([saved_encoding], unknown_encoding, tolerance=0.5)
        
        if results[0]:
            return "100"
        else:
            return "0"
    except Exception as e:
        return "0"

# --- MAIN ---
if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("0")
        sys.exit()

    action = sys.argv[1]
    image_path = sys.argv[2]

    if action == "register":
        print(register_face(image_path))
    elif action == "match":
        if len(sys.argv) > 3:
            saved_hash_file = sys.argv[3]
            print(match_face(image_path, saved_hash_file))
        else:
            print("0")