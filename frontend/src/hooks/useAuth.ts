import { useState, useCallback, useEffect } from 'react';
import { useApi } from './useApi';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  etablissement_id: number;
}

export const useAuth = () => {
  const { post, get } = useApi();
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      loadUser();
    } else {
      setLoading(false);
    }
  }, []);

  const loadUser = async () => {
    try {
      const response = await get('/me');
      setUser(response.data);
      setError(null);
    } catch (err) {
      setError('Failed to load user');
      localStorage.removeItem('auth_token');
    } finally {
      setLoading(false);
    }
  };

  const login = useCallback(async (email: string, password: string) => {
    try {
      setLoading(true);
      const response = await post('/login', { email, password });
      localStorage.setItem('auth_token', response.data.access_token);
      setUser(response.data.user);
      setError(null);
      return true;
    } catch (err) {
      setError('Invalid credentials');
      return false;
    } finally {
      setLoading(false);
    }
  }, [post]);

  const logout = useCallback(async () => {
    try {
      await post('/logout');
    } catch (err) {
      console.error(err);
    } finally {
      localStorage.removeItem('auth_token');
      setUser(null);
    }
  }, [post]);

  return { user, loading, error, login, logout };
};
