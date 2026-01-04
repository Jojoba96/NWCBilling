import React, { useMemo } from 'react';
import { Trash2, Pause, Play, MessageSquare, Undo2 } from 'lucide-react';
import { Segment } from './SegmentForm';

interface SegmentTableProps {
  segments: Segment[];
  onRemoveSegment: (segmentId: string) => void;
  onFreezeSegment: (segmentId: string) => void;
  onReopenSegment: (segmentId: string) => void;
  onAddNote: (segmentId: string) => void;
  onUndoNote: (segmentId: string) => void;
  selectedSegments: Set<string>;
  onSelectSegment: (segmentId: string, selected: boolean) => void;
  onSelectAll: (selected: boolean) => void;
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'draft':
      return 'bg-gray-100 text-gray-800';
    case 'pending_review':
      return 'bg-yellow-100 text-yellow-800';
    case 'active':
      return 'bg-green-100 text-green-800';
    case 'completed':
      return 'bg-blue-100 text-blue-800';
    case 'rejected':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

export const SegmentTable: React.FC<SegmentTableProps> = ({
  segments,
  onRemoveSegment,
  onFreezeSegment,
  onReopenSegment,
  onAddNote,
  onUndoNote,
  selectedSegments,
  onSelectSegment,
  onSelectAll
}) => {
  const { totalConsumption, totalAmount } = useMemo(() => {
    const total = segments.reduce(
      (acc, seg) => ({
        consumption: acc.consumption + seg.consumption,
        amount: acc.amount + seg.amount
      }),
      { consumption: 0, amount: 0 }
    );
    return total;
  }, [segments]);

  const selectedTotal = useMemo(() => {
    const total = segments
      .filter(seg => selectedSegments.has(seg.id || ''))
      .reduce(
        (acc, seg) => ({
          consumption: acc.consumption + seg.consumption,
          amount: acc.amount + seg.amount
        }),
        { consumption: 0, amount: 0 }
      );
    return total;
  }, [segments, selectedSegments]);

  if (segments.length === 0) {
    return (
      <div className="bg-white rounded-lg shadow-md p-6 mb-6">
        <p className="text-gray-500 text-center py-8">No segments added yet. Add segments above to get started.</p>
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow-md p-6 mb-6">
      <h3 className="text-xl font-bold text-gray-800 mb-4">Bill Segments</h3>

      {/* Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-100 border-b-2 border-gray-300">
            <tr>
              <th className="px-4 py-3 text-left">
                <input
                  type="checkbox"
                  checked={selectedSegments.size === segments.length && segments.length > 0}
                  onChange={(e) => onSelectAll(e.target.checked)}
                  className="w-4 h-4"
                />
              </th>
              <th className="px-4 py-3 text-left font-semibold text-gray-700">Type</th>
              <th className="px-4 py-3 text-right font-semibold text-gray-700">Consumption (m³)</th>
              <th className="px-4 py-3 text-right font-semibold text-gray-700">Amount (SAR)</th>
              <th className="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
              <th className="px-4 py-3 text-left font-semibold text-gray-700">Remarks</th>
              <th className="px-4 py-3 text-center font-semibold text-gray-700">Actions</th>
            </tr>
          </thead>
          <tbody>
            {segments.map((segment) => (
              <tr key={segment.id} className="border-b border-gray-200 hover:bg-gray-50">
                {/* Checkbox */}
                <td className="px-4 py-3">
                  <input
                    type="checkbox"
                    checked={selectedSegments.has(segment.id || '')}
                    onChange={(e) => onSelectSegment(segment.id || '', e.target.checked)}
                    className="w-4 h-4"
                  />
                </td>

                {/* Type */}
                <td className="px-4 py-3 font-medium text-gray-800">{segment.name}</td>

                {/* Consumption */}
                <td className="px-4 py-3 text-right text-gray-800">
                  {segment.consumption.toFixed(2)}
                </td>

                {/* Amount */}
                <td className="px-4 py-3 text-right font-semibold text-green-600">
                  {segment.amount.toFixed(2)} SAR
                </td>

                {/* Status */}
                <td className="px-4 py-3">
                  <span className={`px-3 py-1 rounded-full text-xs font-semibold ${getStatusColor(segment.status)}`}>
                    {segment.status.replace('_', ' ').toUpperCase()}
                  </span>
                </td>

                {/* Remarks */}
                <td className="px-4 py-3 text-gray-600 max-w-xs truncate">
                  {segment.remarks || '-'}
                </td>

                {/* Actions */}
                <td className="px-4 py-3">
                  <div className="flex gap-2 justify-center flex-wrap">
                    <button
                      onClick={() => onRemoveSegment(segment.id || '')}
                      className="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 tooltip"
                      title="Delete"
                    >
                      <Trash2 size={16} />
                    </button>

                    {segment.status === 'draft' && (
                      <button
                        onClick={() => onFreezeSegment(segment.id || '')}
                        className="p-2 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200 tooltip"
                        title="Complete"
                      >
                        <Pause size={16} />
                      </button>
                    )}

                    {segment.status === 'completed' && (
                      <button
                        onClick={() => onReopenSegment(segment.id || '')}
                        className="p-2 bg-yellow-100 text-yellow-600 rounded-lg hover:bg-yellow-200 tooltip"
                        title="Reopen"
                      >
                        <Play size={16} />
                      </button>
                    )}

                    <button
                      onClick={() => onAddNote(segment.id || '')}
                      className="p-2 bg-purple-100 text-purple-600 rounded-lg hover:bg-purple-200 tooltip"
                      title="Add Note"
                    >
                      <MessageSquare size={16} />
                    </button>

                    {segment.remarks && (
                      <button
                        onClick={() => onUndoNote(segment.id || '')}
                        className="p-2 bg-orange-100 text-orange-600 rounded-lg hover:bg-orange-200 tooltip"
                        title="Remove Note"
                      >
                        <Undo2 size={16} />
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Totals */}
      <div className="mt-6 border-t-2 border-gray-300 pt-4">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-blue-50 p-4 rounded-lg">
            <p className="text-sm text-gray-600">Total Segments</p>
            <p className="text-2xl font-bold text-blue-600">{segments.length}</p>
          </div>
          <div className="bg-gray-50 p-4 rounded-lg">
            <p className="text-sm text-gray-600">Total Consumption (m³)</p>
            <p className="text-2xl font-bold text-gray-800">{totalConsumption.toFixed(2)}</p>
          </div>
          <div className="bg-green-50 p-4 rounded-lg">
            <p className="text-sm text-gray-600">Total Bill Amount (SAR)</p>
            <p className="text-2xl font-bold text-green-600">{totalAmount.toFixed(2)}</p>
          </div>
          {selectedSegments.size > 0 && (
            <div className="bg-orange-50 p-4 rounded-lg">
              <p className="text-sm text-gray-600">Selected Total (SAR)</p>
              <p className="text-2xl font-bold text-orange-600">{selectedTotal.amount.toFixed(2)}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
