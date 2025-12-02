"""
LaptopAdvisor Recommendation Engine
Machine Learning-based product recommendations using collaborative and content-based filtering
"""

import pandas as pd
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.preprocessing import StandardScaler
from scipy.sparse import csr_matrix
from sklearn.neighbors import NearestNeighbors
import mysql.connector
from config import Config
import pickle
import os
from datetime import datetime, timedelta

class RecommendationEngine:
    """Main recommendation engine class"""
    
    def __init__(self):
        self.config = Config()
        self.config.ensure_model_dir()
        self.conn = None
        self.products_df = None
        self.ratings_df = None
        self.product_features = None
        self.similarity_matrix = None
        
    def connect_db(self):
        """Establish database connection"""
        try:
            self.conn = mysql.connector.connect(
                host=self.config.DB_HOST,
                user=self.config.DB_USER,
                password=self.config.DB_PASSWORD,
                database=self.config.DB_NAME
            )
            return True
        except Exception as e:
            print(f"Database connection error: {e}")
            return False
    
    def close_db(self):
        """Close database connection"""
        if self.conn and self.conn.is_connected():
            self.conn.close()
    
    def load_data(self):
        """Load product and rating data from database"""
        if not self.connect_db():
            return False
        
        try:
            # Load products
            products_query = """
                SELECT product_id, product_name, brand, price, ram_gb, storage_gb, 
                       display_size, cpu, gpu, primary_use_case
                FROM products
            """
            self.products_df = pd.read_sql(products_query, self.conn)
            
            # Load ratings
            ratings_query = """
                SELECT user_id, product_id, rating
                FROM recommendation_ratings
                WHERE rating IN (-1, 1)
            """
            self.ratings_df = pd.read_sql(ratings_query, self.conn)
            
            self.close_db()
            return True
        except Exception as e:
            print(f"Data loading error: {e}")
            self.close_db()
            return False
    
    def prepare_content_features(self):
        """Prepare product features for content-based filtering"""
        if self.products_df is None:
            return False
        
        # Create feature matrix
        features = pd.DataFrame()
        
        # Numerical features (normalized)
        scaler = StandardScaler()
        numerical_cols = ['price', 'ram_gb', 'storage_gb', 'display_size']
        features[numerical_cols] = scaler.fit_transform(
            self.products_df[numerical_cols].fillna(0)
        )
        
        # Categorical features (one-hot encoding)
        features = pd.concat([
            features,
            pd.get_dummies(self.products_df['brand'], prefix='brand'),
            pd.get_dummies(self.products_df['primary_use_case'], prefix='use_case')
        ], axis=1)
        
        # Text features (simple encoding for CPU/GPU)
        # Check for high-end components
        features['has_high_end_cpu'] = self.products_df['cpu'].str.contains(
            'i7|i9|Ryzen 7|Ryzen 9', case=False, na=False
        ).astype(int)
        
        features['has_dedicated_gpu'] = self.products_df['gpu'].str.contains(
            'RTX|GTX|RX|Radeon', case=False, na=False
        ).astype(int)
        
        self.product_features = features
        
        # Calculate similarity matrix
        self.similarity_matrix = cosine_similarity(features)
        
        return True
    
    def get_content_based_recommendations(self, product_id, n=10):
        """Get recommendations based on product similarity"""
        if self.similarity_matrix is None:
            self.prepare_content_features()
        
        try:
            # Get index of the product
            idx = self.products_df[self.products_df['product_id'] == product_id].index[0]
            
            # Get similarity scores
            sim_scores = list(enumerate(self.similarity_matrix[idx]))
            
            # Sort by similarity
            sim_scores = sorted(sim_scores, key=lambda x: x[1], reverse=True)
            
            # Get top N similar products (excluding itself)
            sim_scores = sim_scores[1:n+1]
            
            # Get product indices
            product_indices = [i[0] for i in sim_scores]
            scores = [i[1] for i in sim_scores]
            
            # Return product IDs and scores
            recommendations = self.products_df.iloc[product_indices]['product_id'].tolist()
            
            return [
                {'product_id': int(pid), 'score': float(score), 'method': 'content_based'}
                for pid, score in zip(recommendations, scores)
            ]
        except Exception as e:
            print(f"Content-based recommendation error: {e}")
            return []
    
    def get_collaborative_recommendations(self, user_id, n=10):
        """Get recommendations using collaborative filtering"""
        if self.ratings_df is None or len(self.ratings_df) < self.config.MIN_RATINGS_FOR_COLLABORATIVE:
            return []
        
        try:
            # Create user-item matrix
            user_item_matrix = self.ratings_df.pivot_table(
                index='user_id',
                columns='product_id',
                values='rating',
                fill_value=0
            )
            
            # Check if user exists
            if user_id not in user_item_matrix.index:
                return []
            
            # Convert to sparse matrix for efficiency
            sparse_matrix = csr_matrix(user_item_matrix.values)
            
            # Use KNN for finding similar users
            model_knn = NearestNeighbors(metric='cosine', algorithm='brute', n_neighbors=min(10, len(user_item_matrix)))
            model_knn.fit(sparse_matrix)
            
            # Get user index
            user_idx = user_item_matrix.index.get_loc(user_id)
            
            # Find similar users
            distances, indices = model_knn.kneighbors(
                user_item_matrix.iloc[user_idx, :].values.reshape(1, -1),
                n_neighbors=min(6, len(user_item_matrix))
            )
            
            # Get products liked by similar users
            similar_users = indices.flatten()[1:]  # Exclude the user itself
            
            # Aggregate ratings from similar users
            recommendations = {}
            for similar_user_idx in similar_users:
                similar_user_id = user_item_matrix.index[similar_user_idx]
                similar_user_ratings = self.ratings_df[
                    (self.ratings_df['user_id'] == similar_user_id) & 
                    (self.ratings_df['rating'] == 1)
                ]
                
                for _, row in similar_user_ratings.iterrows():
                    product_id = row['product_id']
                    # Don't recommend products the user has already rated
                    user_rated = self.ratings_df[
                        (self.ratings_df['user_id'] == user_id) & 
                        (self.ratings_df['product_id'] == product_id)
                    ]
                    
                    if len(user_rated) == 0:
                        if product_id not in recommendations:
                            recommendations[product_id] = 0
                        recommendations[product_id] += 1
            
            # Sort by frequency
            sorted_recommendations = sorted(
                recommendations.items(),
                key=lambda x: x[1],
                reverse=True
            )[:n]
            
            # Normalize scores
            max_score = max([score for _, score in sorted_recommendations]) if sorted_recommendations else 1
            
            return [
                {
                    'product_id': int(pid),
                    'score': float(score / max_score),
                    'method': 'collaborative'
                }
                for pid, score in sorted_recommendations
            ]
        except Exception as e:
            print(f"Collaborative filtering error: {e}")
            return []
    
    def get_hybrid_recommendations(self, user_id, use_case=None, n=10):
        """Get hybrid recommendations combining collaborative and content-based"""
        # Get collaborative recommendations
        collab_recs = self.get_collaborative_recommendations(user_id, n=n*2)
        
        # If not enough collaborative recommendations, use content-based
        if len(collab_recs) < n:
            # Get user's liked products
            user_liked = self.ratings_df[
                (self.ratings_df['user_id'] == user_id) & 
                (self.ratings_df['rating'] == 1)
            ]['product_id'].tolist()
            
            content_recs = []
            if user_liked:
                # Get recommendations based on last liked product
                last_liked = user_liked[-1]
                content_recs = self.get_content_based_recommendations(last_liked, n=n*2)
            
            # If still not enough, get popular products in use case
            if len(content_recs) < n and use_case:
                popular_recs = self.get_popular_by_use_case(use_case, n=n)
                content_recs.extend(popular_recs)
            
            # Combine recommendations
            all_recs = {}
            
            # Add collaborative with higher weight
            for rec in collab_recs:
                all_recs[rec['product_id']] = rec['score'] * self.config.COLLABORATIVE_WEIGHT
            
            # Add content-based with lower weight
            for rec in content_recs:
                pid = rec['product_id']
                if pid in all_recs:
                    all_recs[pid] += rec['score'] * self.config.CONTENT_WEIGHT
                else:
                    all_recs[pid] = rec['score'] * self.config.CONTENT_WEIGHT
            
            # Sort by combined score
            sorted_recs = sorted(all_recs.items(), key=lambda x: x[1], reverse=True)[:n]
            
            return [
                {
                    'product_id': int(pid),
                    'score': float(score),
                    'method': 'hybrid'
                }
                for pid, score in sorted_recs
            ]
        
        return collab_recs[:n]
    
    def get_popular_by_use_case(self, use_case, n=10):
        """Get popular products for a specific use case"""
        if self.products_df is None:
            return []
        
        # Filter by use case
        filtered_products = self.products_df[
            self.products_df['primary_use_case'] == use_case
        ]
        
        # Get rating counts
        if self.ratings_df is not None and len(self.ratings_df) > 0:
            rating_counts = self.ratings_df[
                self.ratings_df['rating'] == 1
            ].groupby('product_id').size().reset_index(name='count')
            
            # Merge with products
            popular = filtered_products.merge(rating_counts, on='product_id', how='left')
            popular['count'] = popular['count'].fillna(0)
            
            # Sort by count and price (lower is better)
            popular = popular.sort_values(['count', 'price'], ascending=[False, True])
        else:
            # If no ratings, sort by price
            popular = filtered_products.sort_values('price')
        
        # Get top N
        top_products = popular.head(n)
        
        return [
            {
                'product_id': int(row['product_id']),
                'score': 0.5,  # Default score for popular items
                'method': 'popular'
            }
            for _, row in top_products.iterrows()
        ]
    
    def save_model(self):
        """Save the trained model components"""
        model_data = {
            'similarity_matrix': self.similarity_matrix,
            'product_features': self.product_features,
            'products_df': self.products_df,
            'timestamp': datetime.now()
        }
        
        model_path = os.path.join(self.config.MODEL_PATH, 'recommendation_model.pkl')
        with open(model_path, 'wb') as f:
            pickle.dump(model_data, f)
        
        print(f"Model saved to {model_path}")
    
    def load_model(self):
        """Load a previously trained model"""
        model_path = os.path.join(self.config.MODEL_PATH, 'recommendation_model.pkl')
        
        if not os.path.exists(model_path):
            return False
        
        try:
            with open(model_path, 'rb') as f:
                model_data = pickle.load(f)
            
            # Check if model is not too old (24 hours)
            if datetime.now() - model_data['timestamp'] > timedelta(hours=self.config.CACHE_EXPIRY_HOURS):
                return False
            
            self.similarity_matrix = model_data['similarity_matrix']
            self.product_features = model_data['product_features']
            self.products_df = model_data['products_df']
            
            print("Model loaded successfully")
            return True
        except Exception as e:
            print(f"Model loading error: {e}")
            return False
    
    def train(self):
        """Train/update the recommendation model"""
        print("Training recommendation model...")
        
        # Load data
        if not self.load_data():
            return False
        
        # Prepare features
        if not self.prepare_content_features():
            return False
        
        # Save model
        self.save_model()
        
        print("Model training complete!")
        return True


if __name__ == "__main__":
    # Test the recommendation engine
    engine = RecommendationEngine()
    
    # Train the model
    if engine.train():
        print("\n✓ Model trained successfully!")
        
        # Test recommendations
        print("\nTesting recommendations for user_id=1...")
        recommendations = engine.get_hybrid_recommendations(user_id=1, use_case='Gaming', n=5)
        
        print(f"\nFound {len(recommendations)} recommendations:")
        for i, rec in enumerate(recommendations, 1):
            print(f"{i}. Product ID: {rec['product_id']}, Score: {rec['score']:.3f}, Method: {rec['method']}")
    else:
        print("\n✗ Model training failed!")
