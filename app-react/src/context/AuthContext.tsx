import React, { createContext, useContext, useState, useEffect } from 'react';

interface CustomerAuth {
  user_id: number;
  account_id: number;
  account_number: string;
  full_name: string;
  email: string;
  phone_number: string;
  account_type: string;
}

interface AuthContextType {
  auth: CustomerAuth | null;
  isLoading: boolean;
  login: (nationalId: string, password: string) => Promise<boolean>;
  logout: () => void;
  isAuthenticated: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [auth, setAuth] = useState<CustomerAuth | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  // Load auth from localStorage on mount
  useEffect(() => {
    const storedAuth = localStorage.getItem('customerAuth');
    if (storedAuth) {
      try {
        setAuth(JSON.parse(storedAuth));
      } catch (e) {
        localStorage.removeItem('customerAuth');
      }
    }
  }, []);

  const login = async (nationalId: string, password: string): Promise<boolean> => {
    setIsLoading(true);
    try {
      const response = await fetch('/NWCBilling/api/login.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          national_id: nationalId,
          password: password,
        }),
      });

      const data = await response.json();

      if (data.success && data.user_id) {
        const authData: CustomerAuth = {
          user_id: data.user_id,
          account_id: data.account_id,
          account_number: data.account_number,
          full_name: data.full_name,
          email: data.email,
          phone_number: data.phone_number,
          account_type: data.account_type,
        };
        setAuth(authData);
        localStorage.setItem('customerAuth', JSON.stringify(authData));
        return true;
      } else {
        return false;
      }
    } catch (error) {
      console.error('Login error:', error);
      return false;
    } finally {
      setIsLoading(false);
    }
  };

  const logout = () => {
    setAuth(null);
    localStorage.removeItem('customerAuth');
  };

  return (
    <AuthContext.Provider
      value={{
        auth,
        isLoading,
        login,
        logout,
        isAuthenticated: !!auth,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
};
