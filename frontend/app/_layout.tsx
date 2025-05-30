// app/_layout.tsx - VERSION BRUTALE QUI MARCHE
import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { useFonts } from 'expo-font';
import { Slot, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
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

// 🔥 COMPOSANT QUI GÈRE LES REDIRECTIONS BRUTALEMENT
function AuthRedirectHandler({ children }: { children: React.ReactNode }) {
  const { user, isLoading } = useAuth();
  const router = useRouter();
  const segments = useSegments();

  useEffect(() => {
    if (isLoading) return; // Attendre la fin du loading

    const inAuthGroup = segments[0] === '(auth)';
    const inAppGroup = segments[0] === '(app)';

    console.log('🔍 Redirect check:', {
      user: user?.name || 'null',
      inAuthGroup,
      inAppGroup,
      segments
    });

    // 🔥 REDIRECTIONS BRUTALES
    if (!user && inAppGroup) {
      console.log('❌ Not authenticated, redirecting to login');
      router.replace('/(auth)/login');
    } else if (user && inAuthGroup) {
      console.log('✅ Authenticated, redirecting to app');
      router.replace('/(app)/(tabs)');
    } else if (!inAuthGroup && !inAppGroup) {
      // Premier chargement
      if (user) {
        console.log('🏠 Initial redirect to app');
        router.replace('/(app)/(tabs)');
      } else {
        console.log('🏠 Initial redirect to login');
        router.replace('/(auth)/login');
      }
    }
  }, [user, isLoading, segments]);

  // Écran de chargement pendant la vérification
  if (isLoading) {
    return <LoadingScreen />;
  }

  return <>{children}</>;
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
        <AuthRedirectHandler>
          <Slot />
        </AuthRedirectHandler>
        <StatusBar style="auto" />
      </AuthProvider>
    </ThemeProvider>
  );
}