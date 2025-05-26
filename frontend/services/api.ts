import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';
import { storageService } from './storage.service';

// Configuration de base de l'API
const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL || 'http://localhost:8000/api';

class ApiService {
  private api: AxiosInstance;

  constructor() {
    this.api = axios.create({
      baseURL: API_BASE_URL,
      timeout: 10000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // Headers requis pour Laravel Sanctum
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    this.setupInterceptors();
  }

  private setupInterceptors(): void {
    // Intercepteur de requête - Ajouter le token d'authentification
    this.api.interceptors.request.use(
      async (config) => {
        const token = await storageService.getToken();
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        
        // Log des requêtes en développement
        if (__DEV__) {
          console.log(`🚀 API Request: ${config.method?.toUpperCase()} ${config.url}`);
          if (config.data) {
            console.log('📦 Request Data:', config.data);
          }
        }
        
        return config;
      },
      (error) => {
        console.error('❌ Request Error:', error);
        return Promise.reject(error);
      }
    );

    // Intercepteur de réponse - Gestion des erreurs et logs
    this.api.interceptors.response.use(
      (response: AxiosResponse) => {
        // Log des réponses en développement
        if (__DEV__) {
          console.log(`✅ API Response: ${response.status} ${response.config.url}`);
        }
        return response;
      },
      async (error) => {
        const originalRequest = error.config;

        // Log des erreurs
        if (__DEV__) {
          console.error(`❌ API Error: ${error.response?.status} ${error.config?.url}`);
          console.error('Error details:', error.response?.data);
        }

        // Gestion des erreurs d'authentification
        if (error.response?.status === 401 && !originalRequest._retry) {
          originalRequest._retry = true;

          // Token invalide ou expiré
          await storageService.removeToken();
          
          // Émettre un événement pour rediriger vers la page de connexion
          // Sera géré par le store/context d'authentification
          if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('auth:logout'));
          }
          
          return Promise.reject(new Error('Session expirée, veuillez vous reconnecter'));
        }

        // Gestion des autres erreurs HTTP
        const errorMessage = this.getErrorMessage(error);
        return Promise.reject(new Error(errorMessage));
      }
    );
  }

  private getErrorMessage(error: any): string {
    if (error.response?.data?.message) {
      return error.response.data.message;
    }
    
    switch (error.response?.status) {
      case 400:
        return 'Requête invalide';
      case 403:
        return 'Accès refusé';
      case 404:
        return 'Ressource non trouvée';
      case 422:
        return 'Données invalides';
      case 429:
        return 'Trop de requêtes, veuillez patienter';
      case 500:
        return 'Erreur serveur';
      case 503:
        return 'Service temporairement indisponible';
      default:
        if (!error.response) {
          return 'Erreur de connexion réseau';
        }
        return 'Une erreur est survenue';
    }
  }

  // Méthodes HTTP publiques
  public async get<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.get<T>(url, config);
    return response.data;
  }

  public async post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.post<T>(url, data, config);
    return response.data;
  }

  public async put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.put<T>(url, data, config);
    return response.data;
  }

  public async delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.delete<T>(url, config);
    return response.data;
  }

  public async patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.patch<T>(url, data, config);
    return response.data;
  }

  // Méthode spéciale pour l'upload de fichiers
  public async uploadFile<T = any>(
    url: string,
    formData: FormData,
    config?: AxiosRequestConfig
  ): Promise<T> {
    const response = await this.api.post<T>(url, formData, {
      ...config,
      headers: {
        ...config?.headers,
        'Content-Type': 'multipart/form-data',
      },
      // Timeout plus long pour les uploads
      timeout: 30000,
    });
    return response.data;
  }

  // Méthode pour récupérer l'instance Axios si nécessaire
  public get instance(): AxiosInstance {
    return this.api;
  }

  // Méthode pour changer l'URL de base (utile pour les tests)
  public setBaseURL(baseURL: string): void {
    this.api.defaults.baseURL = baseURL;
  }
}

// Instance singleton de l'API
export const apiService = new ApiService();
export default apiService;