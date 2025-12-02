"""
LaptopAdvisor Recommendation Engine Package
"""

__version__ = '1.0.0'
__author__ = 'LaptopAdvisor Team'

from .recommender import RecommendationEngine
from .config import Config

__all__ = ['RecommendationEngine', 'Config']
