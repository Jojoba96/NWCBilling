import React, { useState } from 'react';
import { api } from '../../services/api';
import { Plus, AlertCircle } from 'lucide-react';

export interface Segment {
  id?: string;
  name: string;
  consumption: number;
  amount: number;
  status: string;
  remarks: string;
}

interface SegmentFormProps {
  accountId: number | null;
  onSegmentAdd: (segment: Segment) => void;
  isLoading: boolean;
}

export const SegmentForm: React.FC<SegmentFormProps> = ({
  accountId,
  onSegmentAdd,
  isLoading
}) => {
  const [consumption, setConsumption] = useState('');
  const [remarks, setRemarks] = useState('');
  const [error, setError] = useState('');
  const [isCalculating, setIsCalculating] = useState(false);

  const handleAddSegment = async () => {
    if (!accountId) {
      setError('Please search for a customer first');
      return;
    }

    if (!consumption || parseFloat(consumption) <= 0) {
      setError('Please enter valid consumption');
      return;
    }

    try {
      setError('');
      setIsCalculating(true);

      const consumptionValue = parseFloat(consumption);

      // Calculate Water Supply amount
      const waterResponse = await api.post('/Employee.php', {
        action: 'calculate_segment_amount',
        consumption: consumptionValue,
        segment_type: 'Water Supply',
        account_type: 'Residential' // TODO: Get from customer info
      });

      const waterAmount = waterResponse.data.data?.amount || 0;

      // Create Water Supply segment
      const waterSegment: Segment = {
        id: `water_${Date.now()}`,
        name: 'Water Supply',
        consumption: consumptionValue,
        amount: waterAmount,
        status: 'draft',
        remarks: remarks
      };

      onSegmentAdd(waterSegment);

      // AUTO-ADD Sewage segment (same consumption, calculated automatically)
      const sewageResponse = await api.post('/Employee.php', {
        action: 'calculate_segment_amount',
        consumption: consumptionValue,
        segment_type: 'Sewage',
        account_type: 'Residential'
      });

      const sewageAmount = sewageResponse.data.data?.amount || 0;

      const sewageSegment: Segment = {
        id: `sewage_${Date.now()}`,
        name: 'Sewage',
        consumption: consumptionValue,
        amount: sewageAmount,
        status: 'draft',
        remarks: remarks
      };

      onSegmentAdd(sewageSegment);

      // Clear form
      setConsumption('');
      setRemarks('');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Error adding segment');
    } finally {
      setIsCalculating(false);
    }
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      handleAddSegment();
    }
  };

  return (
    <div className="bg-white rounded-lg shadow-md p-6 mb-6">
      <h3 className="text-xl font-bold text-gray-800 mb-4">Add Water Supply & Sewage</h3>
      <p className="text-sm text-gray-600 mb-4">
        ℹ️ Enter water consumption - sewage will be automatically added with the same consumption
      </p>

      {error && (
        <div className="mb-4 flex items-center gap-2 text-red-600 bg-red-50 p-3 rounded-lg">
          <AlertCircle size={18} />
          <span>{error}</span>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        {/* Consumption Input */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Water Consumption (m³)
          </label>
          <input
            type="number"
            step="0.01"
            value={consumption}
            onChange={(e) => setConsumption(e.target.value)}
            onKeyPress={handleKeyPress}
            placeholder="e.g., 100.50"
            disabled={!accountId || isLoading}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100"
          />
        </div>

        {/* Remarks Input */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Remarks (Optional)
          </label>
          <input
            type="text"
            value={remarks}
            onChange={(e) => setRemarks(e.target.value)}
            onKeyPress={handleKeyPress}
            placeholder="Any notes..."
            disabled={!accountId || isLoading}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100"
          />
        </div>

        {/* Add Button */}
        <div className="flex items-end">
          <button
            onClick={handleAddSegment}
            disabled={!accountId || isLoading || isCalculating}
            className="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-400 flex items-center justify-center gap-2 font-medium"
          >
            <Plus size={20} />
            {isCalculating ? 'Adding...' : 'Add Supply & Sewage'}
          </button>
        </div>
      </div>

      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p className="text-sm text-blue-800">
          <strong>How it works:</strong> When you enter consumption, the system automatically creates TWO segments:
        </p>
        <ul className="text-sm text-blue-800 mt-2 ml-4 list-disc">
          <li>Water Supply (with your entered consumption)</li>
          <li>Sewage (same consumption, auto-calculated)</li>
        </ul>
        <p className="text-sm text-blue-800 mt-2">
          Both appear in the table below and will be included in ONE final bill.
        </p>
      </div>
    </div>
  );
};
