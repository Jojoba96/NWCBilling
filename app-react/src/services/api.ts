import axios from 'axios';

const API_BASE_URL = '/NWCBilling';

const axiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true, // For session cookies
});

// Add request interceptor to include auth token if available
axiosInstance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Add response interceptor for error handling
axiosInstance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Redirect to login if unauthorized
      localStorage.removeItem('auth_token');
      window.location.href = '/NWCBilling/app-react/#/login';
    }
    return Promise.reject(error);
  }
);

export const api = {
  // Generic POST request
  post: (url: string, data: any) => {
    return axiosInstance.post(url, data);
  },

  // Generic GET request
  get: (url: string, params?: any) => {
    return axiosInstance.get(url, { params });
  },

  // Authentication APIs
  login: (credentials: { username: string; password: string; role: number }) => {
    return axiosInstance.post('/Employee.php', {
      action: 'customer_login',
      ...credentials,
    });
  },

  logout: () => {
    return axiosInstance.post('/Employee.php', {
      action: 'customer_logout',
    });
  },

  // Customer APIs
  getCustomerInfo: (accountId: number) => {
    return axiosInstance.post('/Employee.php', {
      action: 'get_account_info',
      account_id: accountId,
    });
  },

  getCustomerBills: (accountId: number) => {
    return axiosInstance.post('/Employee.php', {
      action: 'get_all_customer_bills',
      account_id: accountId,
    });
  },

  // Bill APIs
  calculateSegmentAmount: (
    consumption: number,
    segmentType: string,
    accountType: string
  ) => {
    return axiosInstance.post('/Employee.php', {
      action: 'calculate_segment_amount',
      consumption,
      segment_type: segmentType,
      account_type: accountType,
    });
  },

  saveSegment: (
    accountId: number,
    segment: {
      name: string;
      consumption: number;
      amount: number;
      remarks: string;
    }
  ) => {
    return axiosInstance.post('/Employee.php', {
      action: 'save_segment',
      account_id: accountId,
      ...segment,
    });
  },

  submitBillForReview: (
    accountId: number,
    segments: Array<{
      name: string;
      consumption: number;
      amount: number;
      remarks: string;
    }>
  ) => {
    return axiosInstance.post('/Employee.php', {
      action: 'submit_bill_for_review',
      account_id: accountId,
      segments,
    });
  },

  deleteSegments: (segmentIds: number[]) => {
    return axiosInstance.post('/Employee.php', {
      action: 'delete_segments',
      segment_ids: segmentIds,
    });
  },

  freezeCompleteSegments: (segmentIds: number[]) => {
    return axiosInstance.post('/Employee.php', {
      action: 'freeze_complete_segments',
      segment_ids: segmentIds,
    });
  },

  reopenSegments: (segmentIds: number[]) => {
    return axiosInstance.post('/Employee.php', {
      action: 'reopen_segments',
      segment_ids: segmentIds,
    });
  },

  addCorrectionNote: (segmentId: number, note: string) => {
    return axiosInstance.post('/Employee.php', {
      action: 'correction_note_segments',
      segment_id: segmentId,
      remarks: note,
    });
  },

  undoCorrectionNote: (segmentId: number) => {
    return axiosInstance.post('/Employee.php', {
      action: 'undo_correction_note_segments',
      segment_id: segmentId,
    });
  },
};
