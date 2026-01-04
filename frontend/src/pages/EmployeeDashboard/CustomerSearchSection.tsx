import React, { useState } from 'react';
import { api } from '../../services/api';
import { AlertCircle, Search } from 'lucide-react';

interface CustomerInfo {
  account_id: number;
  account_number: string;
  full_name: string;
  account_type: string;
  phone_number: string;
  email: string;
}

interface CustomerSearchSectionProps {
  onCustomerSelect: (customer: CustomerInfo) => void;
  isLoading: boolean;
  error: string | null;
}

export const CustomerSearchSection: React.FC<CustomerSearchSectionProps> = ({
  onCustomerSelect,
  isLoading,
  error
}) => {
  const [accountId, setAccountId] = useState('');
  const [searchError, setSearchError] = useState('');
  const [customerInfo, setCustomerInfo] = useState<CustomerInfo | null>(null);

  const handleSearch = async () => {
    if (!accountId.trim()) {
      setSearchError('Please enter an account ID');
      return;
    }

    try {
      setSearchError('');
      const response = await api.post('/Employee.php', {
        action: 'get_account_info',
        account_id: accountId
      });

      if (response.data.success && response.data.data) {
        const customer = response.data.data;
        setCustomerInfo(customer);
        onCustomerSelect(customer);
      } else {
        setSearchError(response.data.message || 'Customer not found');
        setCustomerInfo(null);
      }
    } catch (err: any) {
      setSearchError(err.response?.data?.message || 'Error searching customer');
      setCustomerInfo(null);
    }
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      handleSearch();
    }
  };

  return (
    <div className="bg-white rounded-lg shadow-md p-6 mb-6">
      <h2 className="text-2xl font-bold text-gray-800 mb-6">Create New Bill</h2>

      {/* Search Section */}
      <div className="mb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Search Customer by Account ID
        </label>
        <div className="flex gap-3">
          <div className="flex-1">
            <input
              type="text"
              value={accountId}
              onChange={(e) => setAccountId(e.target.value)}
              onKeyPress={handleKeyPress}
              placeholder="Enter account ID..."
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <button
            onClick={handleSearch}
            disabled={isLoading}
            className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-400 flex items-center gap-2"
          >
            <Search size={20} />
            {isLoading ? 'Searching...' : 'Search'}
          </button>
        </div>
        {searchError && (
          <div className="mt-3 flex items-center gap-2 text-red-600 bg-red-50 p-3 rounded-lg">
            <AlertCircle size={18} />
            <span>{searchError}</span>
          </div>
        )}
        {error && (
          <div className="mt-3 flex items-center gap-2 text-red-600 bg-red-50 p-3 rounded-lg">
            <AlertCircle size={18} />
            <span>{error}</span>
          </div>
        )}
      </div>

      {/* Customer Details */}
      {customerInfo && (
        <div className="bg-gradient-to-r from-blue-50 to-blue-100 p-6 rounded-lg border border-blue-200">
          <h3 className="text-lg font-bold text-gray-800 mb-4">Customer Information</h3>
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
              <p className="text-sm font-medium text-gray-600">Account ID</p>
              <p className="text-lg font-bold text-gray-800">{customerInfo.account_id}</p>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-600">Account Number</p>
              <p className="text-lg font-bold text-gray-800">{customerInfo.account_number}</p>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-600">Full Name</p>
              <p className="text-lg font-bold text-gray-800">{customerInfo.full_name}</p>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-600">Account Type</p>
              <p className="text-lg font-bold text-blue-600">{customerInfo.account_type}</p>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-600">Phone</p>
              <p className="text-lg font-bold text-gray-800">{customerInfo.phone_number}</p>
            </div>
            <div>
              <p className="text-sm font-medium text-gray-600">Email</p>
              <p className="text-lg font-bold text-gray-800">{customerInfo.email}</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
