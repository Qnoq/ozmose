// contexts/AuthProvider.tsx
import { authService, LoginCredentials, RegisterCredentials, User } from '@/services/auth.service';
import { useRouter, useSegments } from 'expo-router';
import React, { createContext, ReactNode, useContext, useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';

interface AuthState {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  error: string | null;
  isInitialized: boolean;
}

interface AuthContextType extends AuthState {
  login: (credentials: LoginCredentials) => Promise<void>;
  register: (credentials: RegisterCredentials) => Promise<void>;
  logout: () => Promise<void>;
  clearError: () => void;
  isPremium: boolean;
  isAdmin: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({
    user: null,
    isLoading: true,
    isAuthenticated: false,
    error: null,
    isInitialized: false,
  });

  const router = useRouter();
  const segments = useSegments();

  // 🔥 INITIALISATION UNIQUEMENT AU DÉMARRAGE
  useEffect(() => {
    initializeAuth();
  }, []);

  // 🔥 NAVIGATION CENTRALISÉE - UNE SEULE SOURCE DE VÉRITÉ
  useEffect(() => {
    if (!state.isInitialized) return;

    const inAuthGroup = segments[0] === '(auth)';
    const inAppGroup = segments[0] === '(app)';
    
    console.log('🔄 Auth state changed:', {
      isAuthenticated: state.isAuthenticated,
      inAuthGroup,
      inAppGroup,
      segments,
    });

    // 🔥 RÈGLES DE NAVIGATION SIMPLES
    if (state.isAuthenticated && (inAuthGroup || (!inAuthGroup && !inAppGroup))) {
      console.log('✅ Redirecting to app');
      setTimeout(() => router.replace('/(app)/(tabs)'), 100);
    } else if (!state.isAuthenticated && (inAppGroup || (!inAuthGroup && !inAppGroup))) {
      console.log('❌ Redirecting to login');
      setTimeout(() => router.replace('/(auth)/login'), 100);
    }
  }, [state.isAuthenticated, state.isInitialized, segments]); // 🔥 AJOUT DE SEGMENTS DANS LES DÉPENDANCES

  const initializeAuth = async () => {
    try {
      console.log('🔄 Initializing auth...');
      
      const isAuth = await authService.isAuthenticated();
      
      if (isAuth) {
        const currentUser = await authService.getCurrentUser();
        console.log('✅ User loaded:', currentUser.name);
        
        setState({
          user: currentUser,
          isLoading: false,
          isAuthenticated: true,
          error: null,
          isInitialized: true,
        });
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

  const login = async (credentials: LoginCredentials) => {
    try {
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      const response = await authService.login(credentials);
      console.log('✅ Login successful:', response.user.name);
      
      // Mettre à jour l'état d'authentification
      setState({
        user: response.user,
        isLoading: false,
        isAuthenticated: true,
        error: null,
        isInitialized: true,
      });
      
      // Redirection immédiate après connexion réussie
      console.log('🔄 Login: Redirecting to app...');
      setTimeout(() => router.replace('/(app)/(tabs)'), 100);
      
    } catch (error: any) {
      setState(prev => ({
        ...prev,
        isLoading: false,
        error: error.message || 'Erreur de connexion',
      }));
      throw error;
    }
  };

  const register = async (credentials: RegisterCredentials) => {
    try {
      setState(prev => ({ ...prev, isLoading: true, error: null }));
      
      const response = await authService.register(credentials);
      console.log('✅ Registration successful:', response.user.name);
      
      // Mettre à jour l'état d'authentification
      setState({
        user: response.user,
        isLoading: false,
        isAuthenticated: true,
        error: null,
        isInitialized: true,
      });
      
      // Redirection immédiate après inscription réussie
      console.log('🔄 Register: Redirecting to app...');
      setTimeout(() => router.replace('/(app)/(tabs)'), 100);
      
    } catch (error: any) {
      setState(prev => ({
        ...prev,
        isLoading: false,
        error: error.message || 'Erreur d\'inscription',
      }));
      throw error;
    }
  };

  const logout = async () => {
    try {
      console.log('🔄 Logging out...');
      setState(prev => ({ ...prev, isLoading: true }));
      
      await authService.logout();
      console.log('✅ Logout successful');
      
      // Mettre à jour l'état d'authentification
      setState({
        user: null,
        isLoading: false,
        isAuthenticated: false,
        error: null,
        isInitialized: true,
      });
      
      // Redirection immédiate après déconnexion
      console.log('🔄 Logout: Redirecting to login...');
      setTimeout(() => router.replace('/(auth)/login'), 100);
      
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
      
      // Redirection même en cas d'erreur
      setTimeout(() => router.replace('/(auth)/login'), 100);
    }
  };

  const clearError = () => {
    setState(prev => ({ ...prev, error: null }));
  };

  // 🔥 ÉCRAN DE CHARGEMENT PENDANT L'INITIALISATION
  if (!state.isInitialized) {
    return (
      <View style={{ 
        flex: 1, 
        justifyContent: 'center', 
        alignItems: 'center',
        backgroundColor: '#fff'
      }}>
        <ActivityIndicator size="large" color="#FF4B8B" />
      </View>
    );
  }

  return (
    <AuthContext.Provider
      value={{
        ...state,
        login,
        register,
        logout,
        clearError,
        isPremium: state.user?.is_premium || false,
        isAdmin: state.user?.is_admin || false,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}