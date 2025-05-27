// app/(auth)/_layout.tsx
import { useAuth } from '@/hooks/useAuth';
import { Redirect, Stack } from 'expo-router';

export default function AuthLayout() {
  const { isAuthenticated, isInitialized } = useAuth();

  // Si l'utilisateur est connecté, rediriger vers l'app
  if (isInitialized && isAuthenticated) {
    return <Redirect href="/(app)/(tabs)" />;
  }

  return (
    <Stack screenOptions={{ 
      headerShown: false,
      presentation: 'card',
      animation: 'slide_from_right',
    }}>
      <Stack.Screen name="login" />
      <Stack.Screen name="register" />
    </Stack>
  );
}