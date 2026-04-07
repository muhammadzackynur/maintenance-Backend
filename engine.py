import cv2
import sys
import base64
import numpy as np

def register_fingerprint(image_path):
    img = cv2.imread(image_path, cv2.IMREAD_GRAYSCALE)
    if img is None:
        return "ERROR"

    orb = cv2.ORB_create()
    keypoints, descriptors = orb.detectAndCompute(img, None)

    if descriptors is None:
        return "ERROR"

    return base64.b64encode(descriptors.tobytes()).decode('utf-8')

def match_fingerprint(image_path, saved_hash_base64):
    img = cv2.imread(image_path, cv2.IMREAD_GRAYSCALE)
    if img is None:
        return "0"

    orb = cv2.ORB_create()
    kp1, desc1 = orb.detectAndCompute(img, None)
    
    if desc1 is None:
        return "0"

    try:
        desc2_bytes = base64.b64decode(saved_hash_base64)
        desc2 = np.frombuffer(desc2_bytes, dtype=np.uint8).reshape(-1, 32)

        bf = cv2.BFMatcher(cv2.NORM_HAMMING, crossCheck=True)
        matches = bf.match(desc1, desc2)

        # Semakin banyak match, semakin mirip. Standar aman: > 30 titik geometri cocok
        if len(matches) > 30:
            return "100"
        else:
            return "0"
    except:
        return "0"

if __name__ == "__main__":
    action = sys.argv[1]
    image_path = sys.argv[2]

    if action == "register":
        print(register_fingerprint(image_path))
    elif action == "match":
        saved_hash = sys.argv[3]
        print(match_fingerprint(image_path, saved_hash))