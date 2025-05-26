import { useEffect, useState } from 'react';
import { authService, LoginCredentials, RegisterCredentials, User } from '../services/auth.service';

interface AuthState {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  error: string | null;
}

export function useAuth() {
  const [state, setState] = useState<AuthState>({
    user: null,
    isLoading: true,
    isAuthenticated: false,
    error: null,
  });

  // Initialisation - vérifier si l'utilisateur est connecté
  useEffect(() => {
    initializeAuth();
  }, []);

  const initializeAuth = async () => {
    try {
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      // Vérifier si l'utilisateur est authentifié
      const isAuth = await authService.isAuthenticated();
      
      if (isAuth) {
        // Récupérer les données utilisateur (du cache ou de l'API)
        const cachedUser = await authService.getCachedUser();
        if (cachedUser) {
          setState({
            user: cachedUser,
            isLoading: false,
            isAuthenticated: true,
            error: null,
          });
        } else {
          // Si pas de cache, récupérer depuis l'API
          const currentUser = await authService.getCurrentUser();
          setState({
            user: currentUser,
            isLoading: false,
            isAuthenticated: true,
            error: null,
          });
        }
      } else {
        setState({
          user: null,
          isLoading: false,
          isAuthenticated: false,
          error: null,
        });
      }
    } catch (error: any) {
      setState({
        user: null,
        isLoading: false,
        isAuthenticated: false,
        error: error.message || 'Erreur d\'initialisation',
      });
    }
  };

  // Connexion
  const login = async (credentials: LoginCredentials) => {
    try {
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      const response = await authService.login(credentials);
      
      setState({
        user: response.user,
        isLoading: false,
        isAuthenticated: true,
        error: null,
      });
      
      return response;
    } catch (error: any) {
      setState(prev => ({
        ...prev,
        isLoading: false,
        error: error.message || 'Erreur de connexion',
      }));
      throw error;
    }
  };

  // Inscription
  const register = async (credentials: RegisterCredentials) => {
    try {
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      const response = await authService.register(credentials);
      
      setState({
        user: response.user,
        isLoading: false,
        isAuthenticated: true,
        error: null,
      });
      
      return response;
    } catch (error: any) {
      setState(prev => ({
        ...prev,
        isLoading: false,
        error: error.message || 'Erreur d\'inscription',
      }));
      throw error;
    }
  };

  // Déconnexion
  const logout = async () => {
    try {
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      await authService.logout();
      
      setState({
        user: null,
        isLoading: false,
        isAuthenticated: false,
        error: null,
      });
    } catch (error: any) {
      // Même si la déconnexion échoue, on considère l'utilisateur comme déconnecté
      setState({
        user: null,
        isLoading: false,
        isAuthenticated: false,
        error: null,
      });
    }
  };

  // Mise à jour du profil
  const updateProfile = async (userData: Partial<User>) => {
    try {
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      const updatedUser = await authService.updateProfile(userData);
      
      setState(prev => ({
        ...prev,
        user: updatedUser,
        isLoading: false,
        error: null,
      }));
      
      return updatedUser;
    } catch (error: any) {
      setState(prev => ({
        ...prev,
        isLoading: false,
        error: error.message || 'Erreur de mise à jour',
      }));
      throw error;
    }
  };

  // Actualiser les données utilisateur
  const refreshUser = async () => {
    try {
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      const currentUser = await authService.getCurrentUser();
      
      setState(prev => ({
        ...prev,
        user: currentUser,
        isLoading: false,
        error: null,
      }));
      
      return currentUser;
    } catch (error: any) {
      setState(prev => ({
        ...prev,
        isLoading: false,
        error: error.message || 'Erreur de rafraîchissement',
      }));
      throw error;
    }
  };

  // Effacer les erreurs
  const clearError = () => {
    setState(prev => ({ ...prev, error: null }));
  };

  return {
    // État
    user: state.user,
    isLoading: state.isLoading,
    isAuthenticated: state.isAuthenticated,
    error: state.error,
    
    // Actions
    login,
    register,
    logout,
    updateProfile,
    refreshUser,
    clearError,
    
    // Méthodes utilitaires
    isPremium: state.user?.is_premium || false,
    isAdmin: state.user?.is_admin || false,
  };
}