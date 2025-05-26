import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { useFonts } from 'expo-font';
import { Stack, router, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import 'react-native-reanimated';

import { useAuth } from '@/hooks/useAuth';
import { useColorScheme } from '@/hooks/useColorScheme';
import { ActivityIndicator, Text, View } from 'react-native';

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const { isAuthenticated, isLoading, isInitialized } = useAuth(); // ← Ajouter isInitialized
  const segments = useSegments();
  
  const [loaded] = useFonts({
    SpaceMono: require('../assets/fonts/SpaceMono-Regular.ttf'),
  });

  useEffect(() => {
    // Ne pas faire de navigation tant que les fonts et l'auth ne sont pas initialisés
    if (!loaded || !isInitialized) return;

    const inAuthGroup = segments[0] === '(auth)';

    if (!isAuthenticated && !inAuthGroup) {
      // Utilisateur pas connecté → rediriger vers login
      router.replace('/(auth)/login');
    } else if (isAuthenticated && inAuthGroup) {
      // Utilisateur connecté mais sur page d'auth → rediriger vers app
      router.replace('/(tabs)');
    }
  }, [isAuthenticated, isInitialized, loaded, segments]); // ← Dépendances mises à jour

  // Afficher le loading tant que les fonts ou l'auth ne sont pas prêts
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
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Screen name="(auth)" options={{ headerShown: false }} />
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="+not-found" />
      </Stack>
      <StatusBar style="auto" />
    </ThemeProvider>
  );
}