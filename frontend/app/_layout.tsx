// app/_layout.tsx - VERSION SIMPLIFIÉE
import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { useFonts } from 'expo-font';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { ActivityIndicator, Text, View } from 'react-native';
import 'react-native-reanimated';

import { AuthProvider, useAuth } from '@/contexts/AuthContext';
import { useColorScheme } from '@/hooks/useColorScheme';

// Écran de chargement
function LoadingScreen() {
  const colorScheme = useColorScheme();
  
  return (
    <View style={{ 
      flex: 1, 
      justifyContent: 'center', 
      alignItems: 'center',
      backgroundColor: colorScheme === 'dark' ? '#151718' : '#fff'
    }}>
      <ActivityIndicator size="large" color="#FF4B8B" />
      <Text style={{ 
        marginTop: 16, 
        fontSize: 16,
        color: colorScheme === 'dark' ? '#ECEDEE' : '#11181C'
      }}>
        Chargement d'Ozmose...
      </Text>
    </View>
  );
}

// Navigation de l'app (utilisateur connecté)
function AppNavigator() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="(app)/(tabs)" />
    </Stack>
  );
}

// Navigation d'authentification (utilisateur non connecté)
function AuthNavigator() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="(auth)/login" />
      <Stack.Screen name="(auth)/register" />
    </Stack>
  );
}

// Navigateur principal avec logique d'authentification
function RootNavigator() {
  const { user, isLoading } = useAuth();

  console.log('🔄 RootNavigator - User:', user?.name || 'null', 'Loading:', isLoading);

  // Écran de chargement pendant la vérification
  if (isLoading) {
    return <LoadingScreen />;
  }

  // 🔥 LOGIQUE SIMPLE : Connecté ou pas connecté
  return user ? <AppNavigator /> : <AuthNavigator />;
}

// Layout principal
export default function RootLayout() {
  const colorScheme = useColorScheme();
  
  const [loaded] = useFonts({
    SpaceMono: require('../assets/fonts/SpaceMono-Regular.ttf'),
  });

  // Attendre le chargement des fonts
  if (!loaded) {
    return <LoadingScreen />;
  }

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <AuthProvider>
        <RootNavigator />
        <StatusBar style="auto" />
      </AuthProvider>
    </ThemeProvider>
  );
}