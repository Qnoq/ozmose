import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { useFonts } from 'expo-font';
import { Stack, router } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import 'react-native-reanimated';

import { useAuth } from '@/hooks/useAuth';
import { useColorScheme } from '@/hooks/useColorScheme';
import { ActivityIndicator, Text, View } from 'react-native';

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const { isAuthenticated, isLoading, isInitialized } = useAuth();
  
  const [loaded] = useFonts({
    SpaceMono: require('../assets/fonts/SpaceMono-Regular.ttf'),
  });

  // 🔥 NAVIGATION SIMPLIFIÉE ET DIRECTE
  useEffect(() => {
    if (!loaded || !isInitialized) {
      console.log('⏳ Waiting for fonts or auth initialization...');
      return;
    }

    console.log('🔄 Auth state changed:', { isAuthenticated, isInitialized });

    // NAVIGATION DIRECTE SANS VÉRIFICATION DE SEGMENTS
    if (isAuthenticated) {
      console.log('✅ User authenticated -> navigate to tabs');
      router.replace('/(tabs)');
    } else {
      console.log('❌ User not authenticated -> navigate to login');
      router.replace('/(auth)/login');
    }
  }, [isAuthenticated, isInitialized, loaded]);

  // Écran de chargement tant que pas prêt
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