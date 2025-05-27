// contexts/AuthContext.tsx - VERSION SIMPLIFIÉE
import { authService, LoginCredentials, RegisterCredentials, User } from '@/services/auth.service';
import React, { createContext, ReactNode, useContext, useEffect, useState } from 'react';

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  login: (credentials: LoginCredentials) => Promise<void>;
  register: (credentials: RegisterCredentials) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // 🔥 VÉRIFICATION AUTH AU DÉMARRAGE - UNE SEULE FOIS
  useEffect(() => {
    checkAuthState();
  }, []);

  const checkAuthState = async () => {
    try {
      console.log('🔄 Checking auth state...');
      
        const isAuth = await authService.isAuthenticated();
        console.log('API says authenticated:', isAuth);
      
      if (isAuth) {
        const currentUser = await authService.getCurrentUser();
        console.log('✅ User found:', currentUser.name);
        setUser(currentUser);
      } else {
        console.log('❌ No user found');
        setUser(null);
      }
    } catch (error) {
      console.error('❌ Auth check failed:', error);
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  };

  const login = async (credentials: LoginCredentials) => {
    try {
      console.log('🔄 Logging in...');
      const response = await authService.login(credentials);
      
      console.log('✅ Login successful:', response.user.name);
      setUser(response.user); // 🔥 SIMPLE : Juste changer l'état
      
    } catch (error: any) {
      console.error('❌ Login failed:', error);
      throw error;
    }
  };

  const register = async (credentials: RegisterCredentials) => {
    try {
      console.log('🔄 Registering...');
      const response = await authService.register(credentials);
      
      console.log('✅ Registration successful:', response.user.name);
      setUser(response.user); // 🔥 SIMPLE : Juste changer l'état
      
    } catch (error: any) {
      console.error('❌ Registration failed:', error);
      throw error;
    }
  };

  const logout = async () => {
    try {
      console.log('🔄 Logging out...');
      await authService.logout();
      
      console.log('✅ Logout successful');
      setUser(null); // 🔥 SIMPLE : Juste changer l'état
      
    } catch (error) {
      console.error('❌ Logout error:', error);
      // Force logout même si erreur
      setUser(null);
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isLoading,
        login,
        register,
        logout,
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