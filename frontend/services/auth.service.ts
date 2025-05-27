// services/auth.service.ts - VERSION CORRIGÉE
import { apiService } from './api';
import { storageService } from './storage.service';

// Types d'authentification basés sur l'API Laravel Ozmose
export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterCredentials {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  avatar?: string;
  bio?: string;
  is_premium?: boolean;
  premium_until?: string;
  subscription_plan?: string;
  subscription_status?: string;
  is_admin?: boolean;
  created_challenges_count?: number;
  participations_count?: number;
  created_challenges?: any[];
  participating_challenges?: any[];
  created_at: string | null;
  updated_at: string;
}

export interface AuthResponse {
  user: User;
  token: string;
  message?: string;
}

class AuthService {
  // Connexion utilisateur
  public async login(credentials: LoginCredentials): Promise<AuthResponse> {
    try {
      const response = await apiService.post<AuthResponse>('/login', credentials);
      
      // Stocker le token et les données utilisateur de manière sécurisée
      await storageService.setToken(response.token);
      await storageService.setUserData(response.user);
      
      return response;
    } catch (error: any) {
      throw this.handleAuthError(error, 'Erreur de connexion');
    }
  }

  // Inscription utilisateur
  public async register(credentials: RegisterCredentials): Promise<AuthResponse> {
    try {
      const response = await apiService.post<AuthResponse>('/register', credentials);
      
      // Stocker le token et les données utilisateur
      await storageService.setToken(response.token);
      await storageService.setUserData(response.user);
      
      return response;
    } catch (error: any) {
      throw this.handleAuthError(error, 'Erreur lors de l\'inscription');
    }
  }

  // Déconnexion utilisateur
  public async logout(): Promise<void> {
    try {
      // Appeler l'API pour invalider le token côté serveur
      await apiService.post('/logout');
    } catch (error) {
      // Même si l'API échoue, on nettoie le stockage local
      console.warn('Erreur lors de la déconnexion côté serveur:', error);
    } finally {
      // Nettoyer le stockage local dans tous les cas
      await storageService.clearAll();
    }
  }

  // Récupérer le profil utilisateur actuel
  public async getCurrentUser(): Promise<User> {
    try {
      const response = await apiService.get<{ data: User }>('/user');
      console.log("user response", response);
      
      const user = response.data;
      console.log("user data", user);
      
      // Mettre à jour les données stockées localement
      await storageService.setUserData(user);
      return user;
    } catch (error) {
      throw new Error('Erreur lors de la récupération du profil');
    }
  }

  // Mettre à jour le profil utilisateur
  public async updateProfile(userData: Partial<User>): Promise<User> {
    try {
      const updatedUser = await apiService.put<User>('/user', userData);
      await storageService.setUserData(updatedUser);
      return updatedUser;
    } catch (error) {
      throw new Error('Erreur lors de la mise à jour du profil');
    }
  }

  // 🔥 MÉTHODE CORRIGÉE - Vérifier si l'utilisateur est connecté
  public async isAuthenticated(): Promise<boolean> {
    try {
      const token = await storageService.getToken();
      if (!token) {
        console.log('❌ No token found');
        return false;
      }
      
      console.log('🔍 Token found, verifying with API...');
      
      // 🔥 TOUJOURS VÉRIFIER AVEC L'API AU DÉMARRAGE
      // Pas d'optimisation hasardeuse avec le cache
      try {
        await this.getCurrentUser();
        console.log('✅ Token valid, user authenticated');
        return true;
      } catch (error) {
        console.log('❌ Token invalid, clearing storage');
        // Token invalide, nettoyer le stockage
        await storageService.clearAll();
        return false;
      }
    } catch (error) {
      console.log('❌ Auth check failed, clearing storage');
      // En cas d'erreur, considérer comme non authentifié
      await storageService.clearAll();
      return false;
    }
  }

  // Récupérer les données utilisateur du cache local
  public async getCachedUser(): Promise<User | null> {
    return await storageService.getUserData();
  }

  // Vérification rapide sans appel API (pour l'initialisation)
  public async hasValidSession(): Promise<boolean> {
    const token = await storageService.getToken();
    const user = await storageService.getUserData();
    return !!(token && user);
  }

  // Demande de réinitialisation de mot de passe
  public async requestPasswordReset(email: string): Promise<{ message: string }> {
    try {
      return await apiService.post('/password/email', { email });
    } catch (error) {
      throw new Error('Erreur lors de la demande de réinitialisation');
    }
  }

  // Réinitialisation de mot de passe
  public async resetPassword(data: {
    email: string;
    password: string;
    password_confirmation: string;
    token: string;
  }): Promise<{ message: string }> {
    try {
      return await apiService.post('/password/reset', data);
    } catch (error) {
      throw new Error('Erreur lors de la réinitialisation du mot de passe');
    }
  }

  // Gestion des erreurs d'authentification
  private handleAuthError(error: any, defaultMessage: string): Error {
    if (error.response?.status === 401) {
      return new Error('Email ou mot de passe incorrect');
    } else if (error.response?.status === 422) {
      const errors = error.response.data.errors;
      if (errors?.email?.[0]) {
        return new Error(errors.email[0]);
      } else if (errors?.password?.[0]) {
        return new Error(errors.password[0]);
      } else if (errors?.name?.[0]) {
        return new Error(errors.name[0]);
      } else {
        return new Error('Données invalides');
      }
    } else if (error.response?.status === 429) {
      return new Error('Trop de tentatives, veuillez patienter');
    } else if (!error.response) {
      return new Error('Erreur de connexion réseau');
    } else {
      return new Error(defaultMessage);
    }
  }
}

export const authService = new AuthService();
export default authService;