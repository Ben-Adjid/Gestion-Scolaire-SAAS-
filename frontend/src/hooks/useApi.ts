import { useCallback } from 'react';
import axios, { AxiosRequestConfig } from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';

export const useApi = () => {
  const getAuthToken = () => {
    return localStorage.getItem('auth_token');
  };

  const request = useCallback(async (
    method: string,
    url: string,
    data?: any,
    config?: AxiosRequestConfig
  ) => {
    const token = getAuthToken();
    const headers = {
      'Content-Type': 'application/json',
      ...(token && { 'Authorization': `Bearer ${token}` }),
      ...config?.headers,
    };

    try {
      const response = await axios({
        method,
        url: `${API_BASE_URL}${url}`,
        data,
        headers,
        ...config,
      });
      return response;
    } catch (error) {
      if (axios.isAxiosError(error) && error.response?.status === 401) {
        localStorage.removeItem('auth_token');
        window.location.href = '/login';
      }
      throw error;
    }
  }, []);

  return {
    get: (url: string, config?: AxiosRequestConfig) => request('GET', url, undefined, config),
    post: (url: string, data?: any, config?: AxiosRequestConfig) => request('POST', url, data, config),
    put: (url: string, data?: any, config?: AxiosRequestConfig) => request('PUT', url, data, config),
    delete: (url: string, config?: AxiosRequestConfig) => request('DELETE', url, undefined, config),
  };
};
