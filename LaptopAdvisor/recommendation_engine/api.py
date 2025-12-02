"""
Flask API Server for LaptopAdvisor Recommendation Engine
Provides REST API endpoints for PHP integration
"""

from flask import Flask, request, jsonify
from recommender import RecommendationEngine
from accessory_recommender import AccessoryRecommender
from config import Config
import threading
import time

app = Flask(__name__)
config = Config()

# Global recommendation engine instances
engine = RecommendationEngine()
accessory_engine = AccessoryRecommender()
model_loaded = False
accessory_model_loaded = False
model_lock = threading.Lock()

def initialize_model():
    """Initialize the recommendation models"""
    global model_loaded, accessory_model_loaded
    
    with model_lock:
        # Initialize laptop recommendation engine
        if not engine.load_model():
            print("No existing model found. Loading data...")
            # Load fresh data
            if engine.load_data():
                engine.prepare_content_features()
                model_loaded = True
                print("Laptop recommendation model initialized successfully!")
            else:
                print("Failed to initialize laptop model")
                model_loaded = False
        else:
            # Also load ratings for collaborative filtering
            engine.load_data()
            model_loaded = True
        
        # Initialize accessory recommendation engine
        print("Loading accessory recommendation engine...")
        if accessory_engine.load_data():
            accessory_engine.prepare_content_features()
            accessory_model_loaded = True
            print("Accessory recommendation model initialized successfully!")
        else:
            print("Failed to initialize accessory model")
            accessory_model_loaded = False

# Initialize model on startup
initialize_model()

@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'model_loaded': model_loaded,
        'timestamp': time.time()
    })

@app.route('/api/recommendations', methods=['POST'])
def get_recommendations():
    """
    Get personalized recommendations for a user
    
    Request body:
    {
        "user_id": 1,
        "use_case": "Gaming",  # optional
        "limit": 10  # optional, default 10
    }
    """
    if not model_loaded:
        return jsonify({
            'error': 'Model not loaded',
            'recommendations': []
        }), 503
    
    try:
        data = request.get_json()
        
        # Validate request
        if not data or 'user_id' not in data:
            return jsonify({
                'error': 'user_id is required'
            }), 400
        
        user_id = int(data['user_id'])
        use_case = data.get('use_case')
        limit = int(data.get('limit', 10))
        
        # Get recommendations
        with model_lock:
            recommendations = engine.get_hybrid_recommendations(
                user_id=user_id,
                use_case=use_case,
                n=limit
            )
        
        return jsonify({
            'success': True,
            'user_id': user_id,
            'recommendations': recommendations,
            'count': len(recommendations)
        })
    
    except Exception as e:
        return jsonify({
            'error': str(e),
            'recommendations': []
        }), 500

@app.route('/api/similar-products', methods=['POST'])
def get_similar_products():
    """
    Get similar products based on a product ID
    
    Request body:
    {
        "product_id": 5,
        "limit": 10  # optional, default 10
    }
    """
    if not model_loaded:
        return jsonify({
            'error': 'Model not loaded',
            'similar_products': []
        }), 503
    
    try:
        data = request.get_json()
        
        # Validate request
        if not data or 'product_id' not in data:
            return jsonify({
                'error': 'product_id is required'
            }), 400
        
        product_id = int(data['product_id'])
        limit = int(data.get('limit', 10))
        
        # Get similar products
        with model_lock:
            similar_products = engine.get_content_based_recommendations(
                product_id=product_id,
                n=limit
            )
        
        return jsonify({
            'success': True,
            'product_id': product_id,
            'similar_products': similar_products,
            'count': len(similar_products)
        })
    
    except Exception as e:
        return jsonify({
            'error': str(e),
            'similar_products': []
        }), 500

@app.route('/api/popular', methods=['POST'])
def get_popular():
    """
    Get popular products for a use case
    
    Request body:
    {
        "use_case": "Gaming",
        "limit": 10  # optional, default 10
    }
    """
    if not model_loaded:
        return jsonify({
            'error': 'Model not loaded',
            'products': []
        }), 503
    
    try:
        data = request.get_json()
        
        # Validate request
        if not data or 'use_case' not in data:
            return jsonify({
                'error': 'use_case is required'
            }), 400
        
        use_case = data['use_case']
        limit = int(data.get('limit', 10))
        
        # Get popular products
        with model_lock:
            products = engine.get_popular_by_use_case(
                use_case=use_case,
                n=limit
            )
        
        return jsonify({
            'success': True,
            'use_case': use_case,
            'products': products,
            'count': len(products)
        })
    
    except Exception as e:
        return jsonify({
            'error': str(e),
            'products': []
        }), 500

@app.route('/api/train', methods=['POST'])
def train_model():
    """
    Retrain the recommendation model with fresh data
    
    Request body:
    {
        "async": true  # optional, run training in background
    }
    """
    try:
        data = request.get_json() or {}
        run_async = data.get('async', False)
        
        if run_async:
            # Run training in background thread
            def train_background():
                global model_loaded
                print("Starting background model training...")
                with model_lock:
                    if engine.train():
                        model_loaded = True
                        print("Background training completed!")
                    else:
                        print("Background training failed!")
            
            thread = threading.Thread(target=train_background)
            thread.daemon = True
            thread.start()
            
            return jsonify({
                'success': True,
                'message': 'Training started in background',
                'async': True
            })
        else:
            # Run training synchronously
            with model_lock:
                global model_loaded
                if engine.train():
                    model_loaded = True
                    return jsonify({
                        'success': True,
                        'message': 'Model trained successfully',
                        'async': False
                    })
                else:
                    return jsonify({
                        'success': False,
                        'error': 'Training failed'
                    }), 500
    
    except Exception as e:
        return jsonify({
            'error': str(e)
        }), 500

@app.route('/api/accessory-recommendations', methods=['POST'])
def get_accessory_recommendations():
    """
    Get AI-powered accessory recommendations for a laptop
    
    Request body:
    {
        "user_id": 1,
        "laptop_id": 3,
        "use_case": "Gaming",  # optional
        "limit": 6,  # optional, default 6
        "method": "hybrid"  # optional: hybrid, content, collaborative, popular
    }
    """
    if not accessory_model_loaded:
        return jsonify({
            'error': 'Accessory recommendation model not loaded',
            'recommendations': []
        }), 503
    
    try:
        data = request.get_json()
        
        # Validate request
        if not data or 'laptop_id' not in data:
            return jsonify({
                'error': 'laptop_id is required'
            }), 400
        
        user_id = int(data.get('user_id', 0))
        laptop_id = int(data['laptop_id'])
        use_case = data.get('use_case')
        limit = int(data.get('limit', 6))
        method = data.get('method', 'hybrid')
        
        # Get recommendations based on method
        with model_lock:
            if method == 'content' or method == 'content_based':
                recommendations = accessory_engine.get_content_based_recommendations(
                    laptop_id=laptop_id,
                    user_use_case=use_case,
                    n=limit
                )
            elif method == 'collaborative':
                recommendations = accessory_engine.get_collaborative_recommendations(
                    user_id=user_id,
                    laptop_id=laptop_id,
                    n=limit
                )
            elif method == 'popular':
                # Get laptop use case first
                laptop_data = accessory_engine.laptops_df[
                    accessory_engine.laptops_df['product_id'] == laptop_id
                ]
                if len(laptop_data) > 0:
                    laptop_use_case = laptop_data.iloc[0]['primary_use_case']
                    recommendations = accessory_engine.get_popular_accessories_by_category(
                        use_case=laptop_use_case,
                        n=limit
                    )
                else:
                    recommendations = []
            else:  # hybrid (default)
                recommendations = accessory_engine.get_hybrid_recommendations(
                    user_id=user_id,
                    laptop_id=laptop_id,
                    user_use_case=use_case,
                    n=limit
                )
        
        return jsonify({
            'success': True,
            'laptop_id': laptop_id,
            'method': method,
            'recommendations': recommendations,
            'count': len(recommendations)
        })
    
    except Exception as e:
        return jsonify({
            'error': str(e),
            'recommendations': []
        }), 500

@app.route('/api/popular-accessories', methods=['POST'])
def get_popular_accessories_endpoint():
    """
    Get popular accessories for a use case
    
    Request body:
    {
        "use_case": "Gaming",
        "limit": 6  # optional, default 6
    }
    """
    if not accessory_model_loaded:
        return jsonify({
            'error': 'Accessory recommendation model not loaded',
            'accessories': []
        }), 503
    
    try:
        data = request.get_json()
        
        # Validate request
        if not data or 'use_case' not in data:
            return jsonify({
                'error': 'use_case is required'
            }), 400
        
        use_case = data['use_case']
        limit = int(data.get('limit', 6))
        
        # Get popular accessories
        with model_lock:
            accessories = accessory_engine.get_popular_accessories_by_category(
                use_case=use_case,
                n=limit
            )
        
        return jsonify({
            'success': True,
            'use_case': use_case,
            'accessories': accessories,
            'count': len(accessories)
        })
    
    except Exception as e:
        return jsonify({
            'error': str(e),
            'accessories': []
        }), 500

@app.route('/api/stats', methods=['GET'])
def get_stats():
    """Get recommendation engine statistics"""
    if not model_loaded:
        return jsonify({
            'error': 'Model not loaded'
        }), 503
    
    try:
        with model_lock:
            stats = {
                'laptop_recommendations': {
                    'total_products': len(engine.products_df) if engine.products_df is not None else 0,
                    'total_ratings': len(engine.ratings_df) if engine.ratings_df is not None else 0,
                    'model_loaded': model_loaded,
                    'features_prepared': engine.product_features is not None,
                    'similarity_matrix_shape': engine.similarity_matrix.shape if engine.similarity_matrix is not None else None
                },
                'accessory_recommendations': {
                    'total_laptops': len(accessory_engine.laptops_df) if accessory_engine.laptops_df is not None else 0,
                    'total_accessories': len(accessory_engine.accessories_df) if accessory_engine.accessories_df is not None else 0,
                    'total_orders': len(accessory_engine.orders_df) if accessory_engine.orders_df is not None else 0,
                    'model_loaded': accessory_model_loaded,
                    'features_prepared': accessory_engine.laptop_features is not None
                }
            }
        
        return jsonify(stats)
    
    except Exception as e:
        return jsonify({
            'error': str(e)
        }), 500

@app.errorhandler(404)
def not_found(error):
    """Handle 404 errors"""
    return jsonify({
        'error': 'Endpoint not found'
    }), 404

@app.errorhandler(500)
def internal_error(error):
    """Handle 500 errors"""
    return jsonify({
        'error': 'Internal server error'
    }), 500

if __name__ == '__main__':
    print(f"""
    ╔════════════════════════════════════════════════════════╗
    ║   LaptopAdvisor Recommendation Engine API Server      ║
    ║                                                        ║
    ║   Running on: http://{config.FLASK_HOST}:{config.FLASK_PORT}              ║
    ║   Debug mode: {config.FLASK_DEBUG}                                   ║
    ╚════════════════════════════════════════════════════════╝
    
    Available endpoints:
    - GET  /api/health                    - Health check
    - POST /api/recommendations           - Get personalized laptop recommendations
    - POST /api/similar-products          - Get similar laptops
    - POST /api/popular                   - Get popular laptops by use case
    - POST /api/accessory-recommendations - Get AI-powered accessory recommendations
    - POST /api/popular-accessories       - Get popular accessories by use case
    - POST /api/train                     - Retrain the model
    - GET  /api/stats                     - Get engine statistics
    """)
    
    app.run(
        host=config.FLASK_HOST,
        port=config.FLASK_PORT,
        debug=config.FLASK_DEBUG
    )
