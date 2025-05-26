import { authService, LoginCredentials, RegisterCredentials, User } from '@/services/auth.service';
import { useEffect, useState } from 'react';

interface AuthState {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  error: string | null;
  isInitialized: boolean;
}

export function useAuth() {
  const [state, setState] = useState<AuthState>({
    user: null,
    isLoading: true,
    isAuthenticated: false,
    error: null,
    isInitialized: false,
  });

  // 🔥 SIMPLIFIER L'INITIALISATION
  useEffect(() => {
    initializeAuth();
  }, []);

  const initializeAuth = async () => {
    try {
      console.log('🔄 Initializing auth...');
      
      const isAuth = await authService.isAuthenticated();
      console.log('🔄 Is authenticated:', isAuth);
      
      if (isAuth) {
        try {
          const currentUser = await authService.getCurrentUser();
          console.log('✅ User loaded:', currentUser.name);
          
          setState({
            user: currentUser,
            isLoading: false,
            isAuthenticated: true,
            error: null,
            isInitialized: true,
          });
        } catch (error) {
          console.log('❌ Failed to get user, logout');
          // Si on ne peut pas récupérer l'utilisateur, déconnecter
          await authService.logout();
          setState({
            user: null,
            isLoading: false,
            isAuthenticated: false,
            error: null,
            isInitialized: true,
          });
        }
      } else {
        console.log('❌ Not authenticated');
        setState({
          user: null,
          isLoading: false,
          isAuthenticated: false,
          error: null,
          isInitialized: true,
        });
      }
    } catch (error: any) {
      console.error('❌ Auth initialization error:', error);
      setState({
        user: null,
        isLoading: false,
        isAuthenticated: false,
        error: null,
        isInitialized: true,
      });
    }
  };

  // 🔥 SIMPLIFIER LA CONNEXION
  const login = async (credentials: LoginCredentials) => {
    try {
      console.log('🔄 Logging in...');
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      const response = await authService.login(credentials);
      console.log('✅ Login successful:', response.user.name);
      
      setState({
        user: response.user,
        isLoading: false,
        isAuthenticated: true,
        error: null,
        isInitialized: true,
      });
      
      return response;
    } catch (error: any) {
      console.error('❌ Login error:', error);
      setState(prev => ({
        ...prev,
        isLoading: false,
        error: error.message || 'Erreur de connexion',
      }));
      throw error;
    }
  };

  // 🔥 SIMPLIFIER LA DÉCONNEXION
  const logout = async () => {
    try {
      console.log('🔄 Logging out...');
      setState(prev => ({ ...prev, isLoading: true }));
      
      await authService.logout();
      console.log('✅ Logout successful');
      
      setState({
        user: null,
        isLoading: false,
        isAuthenticated: false,
        error: null,
        isInitialized: true,
      });
    } catch (error: any) {
      console.error('❌ Logout error:', error);
      // Force logout même en cas d'erreur
      setState({
        user: null,
        isLoading: false,
        isAuthenticated: false,
        error: null,
        isInitialized: true,
      });
    }
  };

  const register = async (credentials: RegisterCredentials) => {
    try {
      console.log('🔄 Registering...');
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      const response = await authService.register(credentials);
      console.log('✅ Registration successful:', response.user.name);
      
      setState({
        user: response.user,
        isLoading: false,
        isAuthenticated: true,
        error: null,
        isInitialized: true,
      });
      
      return response;
    } catch (error: any) {
      console.error('❌ Register error:', error);
      setState(prev => ({
        ...prev,
        isLoading: false,
        error: error.message || 'Erreur d\'inscription',
      }));
      throw error;
    }
  };

  const clearError = () => {
    setState(prev => ({ ...prev, error: null }));
  };

  return {
    user: state.user,
    isLoading: state.isLoading,
    isAuthenticated: state.isAuthenticated,
    error: state.error,
    isInitialized: state.isInitialized,
    
    login,
    register,
    logout,
    clearError,
    
    isPremium: state.user?.is_premium || false,
    isAdmin: state.user?.is_admin || false,
  };
}