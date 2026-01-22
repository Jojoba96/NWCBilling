import React from 'react';
import {
  Send,
  FileText,
  Lock,
  Unlock,
  Trash2,
  MessageSquare,
  RotateCcw,
  Download
} from 'lucide-react';

interface BillActionsPanelProps {
  accountId: number | null;
  segmentCount: number;
  totalAmount: number;
  onSubmitForReview: () => void;
  onGenerateBill: () => void;
  onFreezeSelected: () => void;
  onDeleteSelected: () => void;
  onReopenSelected: () => void;
  onAddCorrectionNote: () => void;
  onUndoCorrectionNote: () => void;
  selectedCount: number;
  isLoading: boolean;
  billStatus?: string;
}

export const BillActionsPanel: React.FC<BillActionsPanelProps> = ({
  accountId,
  segmentCount,
  totalAmount,
  onSubmitForReview,
  onGenerateBill,
  onFreezeSelected,
  onDeleteSelected,
  onReopenSelected,
  onAddCorrectionNote,
  onUndoCorrectionNote,
  selectedCount,
  isLoading,
  billStatus = 'draft'
}) => {
  const isDisabled = !accountId || segmentCount === 0 || isLoading;

  return (
    <div className="bg-white rounded-lg shadow-md p-6 mb-6">
      <h3 className="text-xl font-bold text-gray-800 mb-4">Bill Actions</h3>

      {/* Primary Actions */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        {/* Submit for Review */}
        <button
          onClick={onSubmitForReview}
          disabled={isDisabled || billStatus !== 'draft'}
          className="flex items-center gap-3 px-6 py-4 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 disabled:from-gray-400 disabled:to-gray-400 font-bold text-lg transition-all hover:shadow-lg disabled:cursor-not-allowed"
        >
          <Send size={24} />
          <div className="text-left">
            <p className="font-bold">Submit for Review</p>
            <p className="text-sm text-green-100">Send to admin approval</p>
          </div>
        </button>

        {/* Generate Bill */}
        <button
          onClick={onGenerateBill}
          disabled={isDisabled || billStatus !== 'active'}
          className="flex items-center gap-3 px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 disabled:from-gray-400 disabled:to-gray-400 font-bold text-lg transition-all hover:shadow-lg disabled:cursor-not-allowed"
        >
          <FileText size={24} />
          <div className="text-left">
            <p className="font-bold">Generate Bill</p>
            <p className="text-sm text-blue-100">Create PDF for customer</p>
          </div>
        </button>
      </div>

      {/* Segment Actions (show only when segments selected) */}
      {selectedCount > 0 && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6 pb-6 border-b border-gray-200">
          <button
            onClick={onFreezeSelected}
            disabled={isLoading}
            className="flex items-center gap-2 px-4 py-3 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 disabled:opacity-50 text-sm font-medium"
          >
            <Lock size={18} />
            Complete ({selectedCount})
          </button>

          <button
            onClick={onReopenSelected}
            disabled={isLoading}
            className="flex items-center gap-2 px-4 py-3 bg-yellow-100 text-yellow-600 rounded-lg hover:bg-yellow-200 disabled:opacity-50 text-sm font-medium"
          >
            <Unlock size={18} />
            Reopen ({selectedCount})
          </button>

          <button
            onClick={onAddCorrectionNote}
            disabled={isLoading || selectedCount !== 1}
            title={selectedCount !== 1 ? 'Select one segment' : ''}
            className="flex items-center gap-2 px-4 py-3 bg-purple-100 text-purple-600 rounded-lg hover:bg-purple-200 disabled:opacity-50 text-sm font-medium"
          >
            <MessageSquare size={18} />
            Add Note
          </button>

          <button
            onClick={onUndoCorrectionNote}
            disabled={isLoading || selectedCount !== 1}
            title={selectedCount !== 1 ? 'Select one segment' : ''}
            className="flex items-center gap-2 px-4 py-3 bg-orange-100 text-orange-600 rounded-lg hover:bg-orange-200 disabled:opacity-50 text-sm font-medium"
          >
            <RotateCcw size={18} />
            Undo Note
          </button>
        </div>
      )}

      {/* Danger Zone */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <button
          onClick={onDeleteSelected}
          disabled={isLoading || selectedCount === 0}
          className="flex items-center gap-3 px-6 py-3 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 disabled:opacity-50 font-bold transition-all"
        >
          <Trash2 size={20} />
          Delete Selected ({selectedCount})
        </button>

        <button
          onClick={onGenerateBill}
          disabled={isDisabled || billStatus !== 'active'}
          className="flex items-center gap-3 px-6 py-3 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 disabled:opacity-50 font-bold transition-all"
        >
          <Download size={20} />
          Download PDF
        </button>
      </div>

      {/* Bill Info */}
      <div className="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
        <div className="grid grid-cols-3 gap-4">
          <div>
            <p className="text-sm text-gray-600">Total Segments</p>
            <p className="text-2xl font-bold text-gray-800">{segmentCount}</p>
          </div>
          <div>
            <p className="text-sm text-gray-600">Bill Amount</p>
            <p className="text-2xl font-bold text-green-600">{totalAmount.toFixed(2)} SAR</p>
          </div>
          <div>
            <p className="text-sm text-gray-600">Bill Status</p>
            <p className={`text-2xl font-bold ${
              billStatus === 'active' ? 'text-green-600' :
              billStatus === 'pending_review' ? 'text-yellow-600' :
              billStatus === 'rejected' ? 'text-red-600' :
              'text-gray-600'
            }`}>
              {billStatus.replace('_', ' ').toUpperCase()}
            </p>
          </div>
        </div>
      </div>

      {/* Help Text */}
      <div className="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200 text-sm text-blue-800">
        <p>
          <strong>Workflow:</strong> Add segments → Submit for Review → Wait for admin approval → Generate Bill
        </p>
      </div>
    </div>
  );
};
