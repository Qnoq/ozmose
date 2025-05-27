// app/(auth)/register.tsx - VERSION CORRIGÉE
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useAuth } from '@/contexts/AuthContext'; // 🔥 IMPORT CORRIGÉ
import { useThemeColor } from '@/hooks/useThemeColor';
import { Link } from 'expo-router';
import React, { useState } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export default function RegisterScreen() {
  const { register } = useAuth(); // 🔥 SIMPLE : juste récupérer la fonction register
  const backgroundColor = useThemeColor({}, 'background');
  const textColor = useThemeColor({}, 'text');
  
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [isLoading, setIsLoading] = useState(false);
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  const validateForm = () => {
    const errors: Record<string, string> = {};

    if (!formData.name || formData.name.length < 2) {
      errors.name = 'Le nom doit contenir au moins 2 caractères';
    }

    if (!formData.email) {
      errors.email = 'L\'email est requis';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      errors.email = 'Format d\'email invalide';
    }

    if (!formData.password) {
      errors.password = 'Le mot de passe est requis';
    } else if (formData.password.length < 6) {
      errors.password = 'Le mot de passe doit contenir au moins 6 caractères';
    }

    if (formData.password !== formData.password_confirmation) {
      errors.password_confirmation = 'Les mots de passe ne correspondent pas';
    }

    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleRegister = async () => {
    if (!validateForm()) return;
  
    try {
      setIsLoading(true);
      
      await register(formData);
      // 🔥 PAS DE REDIRECTION ! 
      // Le contexte change `user` → RootNavigator affiche AppNavigator automatiquement
      
    } catch (error: any) {
      Alert.alert('Erreur d\'inscription', error.message);
    } finally {
      setIsLoading(false);
    }
  };

  const handleInputChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    if (formErrors[field]) {
      setFormErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor }]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.keyboardAvoid}>
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled">
          
          <View style={styles.header}>
            <Text style={[styles.title, { color: textColor }]}>
              Rejoignez
            </Text>
            <Text style={[styles.appName, { color: '#FF4B8B' }]}>
              Ozmose
            </Text>
            <Text style={[styles.subtitle, { color: textColor }]}>
              Créez votre compte pour commencer l'aventure
            </Text>
          </View>

          <View style={styles.form}>
            <Input
              label="Nom complet"
              placeholder="Votre nom"
              value={formData.name}
              onChangeText={(value) => handleInputChange('name', value)}
              error={formErrors.name}
              autoCapitalize="words"
              autoComplete="name"
              leftIcon="person"
              required
            />

            <Input
              label="Email"
              placeholder="votre@email.com"
              value={formData.email}
              onChangeText={(value) => handleInputChange('email', value)}
              error={formErrors.email}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              leftIcon="envelope"
              required
            />

            <Input
              label="Mot de passe"
              placeholder="Minimum 6 caractères"
              value={formData.password}
              onChangeText={(value) => handleInputChange('password', value)}
              error={formErrors.password}
              secureTextEntry
              autoCapitalize="none"
              autoComplete="new-password"
              leftIcon="lock"
              required
            />

            <Input
              label="Confirmer le mot de passe"
              placeholder="Répétez votre mot de passe"
              value={formData.password_confirmation}
              onChangeText={(value) => handleInputChange('password_confirmation', value)}
              error={formErrors.password_confirmation}
              secureTextEntry
              autoCapitalize="none"
              autoComplete="new-password"
              leftIcon="lock"
              required
            />

            <Button
              title="Créer mon compte"
              onPress={handleRegister}
              loading={isLoading}
              disabled={isLoading}
              variant="primary"
              size="large"
              style={styles.registerButton}
            />

            <Text style={[styles.termsText, { color: textColor }]}>
              En créant un compte, vous acceptez nos{' '}
              <Text style={styles.termsLink}>conditions d'utilisation</Text>
              {' '}et notre{' '}
              <Text style={styles.termsLink}>politique de confidentialité</Text>.
            </Text>
          </View>

          <View style={styles.footer}>
            <Text style={[styles.footerText, { color: textColor }]}>
              Déjà un compte ?
            </Text>
            <Link href="/(auth)/login" asChild>
              <Text style={styles.loginLink}>
                Se connecter
              </Text>
            </Link>
          </View>
          
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  keyboardAvoid: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: 24,
    paddingVertical: 32,
  },
  header: {
    alignItems: 'center',
    marginBottom: 32,
    marginTop: 16,
  },
  title: {
    fontSize: 28,
    fontWeight: '300',
    textAlign: 'center',
  },
  appName: {
    fontSize: 42,
    fontWeight: 'bold',
    textAlign: 'center',
    marginVertical: 8,
  },
  subtitle: {
    fontSize: 16,
    textAlign: 'center',
    opacity: 0.7,
    marginTop: 8,
  },
  form: {
    flex: 1,
    justifyContent: 'center',
  },
  registerButton: {
    marginTop: 8,
    marginBottom: 16,
  },
  termsText: {
    fontSize: 12,
    lineHeight: 18,
    textAlign: 'center',
    opacity: 0.7,
    marginBottom: 16,
  },
  termsLink: {
    color: '#FF4B8B',
    textDecorationLine: 'underline',
  },
  footer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 24,
    gap: 8,
  },
  footerText: {
    fontSize: 16,
  },
  loginLink: {
    color: '#FF4B8B',
    fontSize: 16,
    fontWeight: '600',
    textDecorationLine: 'underline',
  },
});