import os
from dotenv import load_dotenv

load_dotenv()

class Config:
    """Configuration for the recommendation engine"""
    
    # Database Configuration
    DB_HOST = os.getenv('DB_HOST', 'localhost')
    DB_USER = os.getenv('DB_USER', 'root')
    DB_PASSWORD = os.getenv('DB_PASSWORD', '')
    DB_NAME = os.getenv('DB_NAME', 'laptop_advisor_db')
    
    # Flask Configuration
    FLASK_HOST = os.getenv('FLASK_HOST', '127.0.0.1')
    FLASK_PORT = int(os.getenv('FLASK_PORT', 5000))
    FLASK_DEBUG = os.getenv('FLASK_DEBUG', 'False').lower() == 'true'
    
    # Recommendation Engine Configuration
    MIN_RATINGS_FOR_COLLABORATIVE = 5  # Minimum ratings needed for collaborative filtering
    CONTENT_WEIGHT = 0.4  # Weight for content-based recommendations
    COLLABORATIVE_WEIGHT = 0.6  # Weight for collaborative filtering
    
    # Cache Configuration
    CACHE_EXPIRY_HOURS = 24
    
    # Model Configuration
    MODEL_PATH = os.path.join(os.path.dirname(__file__), 'models')
    
    @staticmethod
    def ensure_model_dir():
        """Ensure the models directory exists"""
        os.makedirs(Config.MODEL_PATH, exist_ok=True)
