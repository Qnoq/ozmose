import { useAuth } from '@/hooks/useAuth';
import { Redirect, Stack } from 'expo-router';

export default function AppLayout() {
  const { isAuthenticated, isInitialized } = useAuth();

  // Rediriger vers login si pas authentifié
  if (isInitialized && !isAuthenticated) {
    return <Redirect href="/(auth)/login" />;
  }

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
      <Stack.Screen name="profile" options={{ title: 'Profil', headerShown: true }} />
    </Stack>
  );
}