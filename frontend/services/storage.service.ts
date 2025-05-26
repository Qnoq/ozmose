import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

// Clés de stockage
const STORAGE_KEYS = {
  AUTH_TOKEN: 'auth_token',
  USER_DATA: 'user_data',
  REFRESH_TOKEN: 'refresh_token',
  USER_PREFERENCES: 'user_preferences',
} as const;

class StorageService {
  // Méthodes pour les données sensibles (tokens)
  private async setSecureItem(key: string, value: string): Promise<void> {
    try {
      if (Platform.OS === 'web') {
        // Sur web, utiliser localStorage avec un préfixe
        localStorage.setItem(`secure_${key}`, value);
      } else {
        // Sur mobile, utiliser SecureStore
        await SecureStore.setItemAsync(key, value);
      }
    } catch (error) {
      console.error('Error storing secure item:', error);
      throw error;
    }
  }

  private async getSecureItem(key: string): Promise<string | null> {
    try {
      if (Platform.OS === 'web') {
        return localStorage.getItem(`secure_${key}`);
      } else {
        return await SecureStore.getItemAsync(key);
      }
    } catch (error) {
      console.error('Error retrieving secure item:', error);
      return null;
    }
  }

  private async deleteSecureItem(key: string): Promise<void> {
    try {
      if (Platform.OS === 'web') {
        localStorage.removeItem(`secure_${key}`);
      } else {
        await SecureStore.deleteItemAsync(key);
      }
    } catch (error) {
      console.error('Error deleting secure item:', error);
    }
  }

  // Méthodes pour les données non-sensibles
  private async setItem(key: string, value: string): Promise<void> {
    try {
      await AsyncStorage.setItem(key, value);
    } catch (error) {
      console.error('Error storing item:', error);
      throw error;
    }
  }

  private async getItem(key: string): Promise<string | null> {
    try {
      return await AsyncStorage.getItem(key);
    } catch (error) {
      console.error('Error retrieving item:', error);
      return null;
    }
  }

  private async removeItem(key: string): Promise<void> {
    try {
      await AsyncStorage.removeItem(key);
    } catch (error) {
      console.error('Error removing item:', error);
    }
  }

  // === Méthodes publiques pour l'authentification ===

  // Token d'authentification
  public async setToken(token: string): Promise<void> {
    await this.setSecureItem(STORAGE_KEYS.AUTH_TOKEN, token);
  }

  public async getToken(): Promise<string | null> {
    return await this.getSecureItem(STORAGE_KEYS.AUTH_TOKEN);
  }

  public async removeToken(): Promise<void> {
    await this.deleteSecureItem(STORAGE_KEYS.AUTH_TOKEN);
  }

  // Données utilisateur
  public async setUserData(userData: any): Promise<void> {
    await this.setItem(STORAGE_KEYS.USER_DATA, JSON.stringify(userData));
  }

  public async getUserData(): Promise<any | null> {
    const data = await this.getItem(STORAGE_KEYS.USER_DATA);
    return data ? JSON.parse(data) : null;
  }

  public async removeUserData(): Promise<void> {
    await this.removeItem(STORAGE_KEYS.USER_DATA);
  }

  // Méthode pour nettoyer tout le stockage (déconnexion)
  public async clearAll(): Promise<void> {
    await Promise.all([
      this.removeToken(),
      this.removeUserData(),
    ]);
  }

  // Méthode pour vérifier si l'utilisateur est connecté
  public async isAuthenticated(): Promise<boolean> {
    const token = await this.getToken();
    return !!token;
  }
}

export const storageService = new StorageService();
export default storageService;