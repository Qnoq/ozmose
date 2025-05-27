// app/_layout.tsx
import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { useFonts } from 'expo-font';
import { Slot, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { ActivityIndicator, Text, View } from 'react-native';
import 'react-native-reanimated';

import { useAuth } from '@/hooks/useAuth';
import { useColorScheme } from '@/hooks/useColorScheme';

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const { isAuthenticated, isInitialized } = useAuth();
  const segments = useSegments();
  const router = useRouter();
  
  const [loaded] = useFonts({
    SpaceMono: require('../assets/fonts/SpaceMono-Regular.ttf'),
  });

  // 🔥 GESTION DES REDIRECTIONS AUTOMATIQUES
  useEffect(() => {
    if (!isInitialized || !loaded) return;

    const inAuthGroup = segments[0] === '(auth)';
    const inAppGroup = segments[0] === '(app)';

    console.log('🔄 Navigation check:', {
      isAuthenticated,
      inAuthGroup,
      inAppGroup,
      segments,
    });

    if (isAuthenticated && inAuthGroup) {
      // Utilisateur connecté mais sur les pages auth -> rediriger vers l'app
      console.log('✅ Redirecting to app (user authenticated)');
      router.replace('/(app)/(tabs)');
    } else if (!isAuthenticated && inAppGroup) {
      // Utilisateur non connecté mais dans l'app -> rediriger vers login
      console.log('❌ Redirecting to login (user not authenticated)');
      router.replace('/(auth)/login');
    } else if (!isAuthenticated && !inAuthGroup && !inAppGroup) {
      // Première visite ou état indéterminé -> login
      console.log('🏠 Initial redirect to login');
      router.replace('/(auth)/login');
    } else if (isAuthenticated && !inAuthGroup && !inAppGroup) {
      // Première visite utilisateur connecté -> app
      console.log('🏠 Initial redirect to app');
      router.replace('/(app)/(tabs)');
    }
  }, [isAuthenticated, isInitialized, segments, loaded]);

  // Écran de chargement
  if (!loaded || !isInitialized) {
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

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <Slot />
      <StatusBar style="auto" />
    </ThemeProvider>
  );
}