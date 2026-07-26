import os
import sys

def load_env():
    """
    Loads environment variables from .env file in desktop_pos directory or parent directory.
    """
    pos_dir = os.path.dirname(os.path.abspath(__file__))
    env_paths = [
        os.path.join(pos_dir, ".env"),
        os.path.join(os.path.dirname(pos_dir), ".env")
    ]
    for env_path in env_paths:
        if os.path.exists(env_path):
            try:
                with open(env_path, "r", encoding="utf-8") as f:
                    for line in f:
                        line = line.strip()
                        if not line or line.startswith("#"):
                            continue
                        if "=" in line:
                            key, val = line.split("=", 1)
                            key = key.strip()
                            val = val.strip().strip("'\"")
                            if key and key not in os.environ:
                                os.environ[key] = val
            except Exception as e:
                print(f"[Warning] Failed to read .env file {env_path}: {e}")

def get_server_url(default="https://app.ruxxengas.com"):
    """
    Retrieves the server URL from environment variables (SERVER_URL or API_BASE_URL)
    or falls back to default.
    """
    load_env()
    url = os.environ.get("SERVER_URL") or os.environ.get("API_BASE_URL")
    if url:
        return url
    return default
