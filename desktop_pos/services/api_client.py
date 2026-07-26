import requests
import json

class ApiClient:
    def __init__(self, base_url="http://localhost:8000"):
        self.base_url = base_url.rstrip('/')
        self.api_prefix = f"{self.base_url}/api/v1/pos"

    def set_base_url(self, url):
        self.base_url = url.rstrip('/')
        self.api_prefix = f"{self.base_url}/api/v1/pos"

    def login(self, email, password):
        url = f"{self.api_prefix}/login"
        try:
            response = requests.post(url, json={"email": email, "password": password}, timeout=5)
            return response.json()
        except requests.exceptions.RequestException as e:
            return {"success": False, "message": f"Connection error: {str(e)}"}

    def get_initial_data(self, token=None):
        url = f"{self.api_prefix}/initial-data"
        headers = {}
        if token:
            headers['Authorization'] = f"Bearer {token}"
        try:
            response = requests.get(url, headers=headers, timeout=5)
            return response.json()
        except requests.exceptions.RequestException as e:
            return {"success": False, "message": f"Connection error: {str(e)}"}

    def check_stock(self, token=None):
        url = f"{self.api_prefix}/stock"
        headers = {}
        if token:
            headers['Authorization'] = f"Bearer {token}"
        try:
            response = requests.get(url, headers=headers, timeout=5)
            return response.json()
        except requests.exceptions.RequestException as e:
            return {"success": False, "message": f"Connection error: {str(e)}"}

    def sync_transactions(self, transactions, token=None):
        url = f"{self.api_prefix}/sync-transactions"
        headers = {'Content-Type': 'application/json'}
        if token:
            headers['Authorization'] = f"Bearer {token}"
        try:
            response = requests.post(url, json={"transactions": transactions}, headers=headers, timeout=10)
            return response.json()
        except requests.exceptions.RequestException as e:
            return {"success": False, "message": f"Connection error: {str(e)}"}
