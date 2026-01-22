import React, { useState, useCallback } from 'react';
import { api } from '../../services/api';
import { CustomerSearchSection } from './CustomerSearchSection';
import { SegmentForm, Segment } from './SegmentForm';
import { SegmentTable } from './SegmentTable';
import { BillActionsPanel } from './BillActionsPanel';
import { ConfirmModal, InputModal, SuccessMessage, ErrorMessage } from './Modals';

interface CustomerInfo {
  account_id: number;
  account_number: string;
  full_name: string;
  account_type: string;
  phone_number: string;
  email: string;
}

const EmployeeDashboard: React.FC = () => {
  console.log('EmployeeDashboard loaded!');
  // State Management
  const [customer, setCustomer] = useState<CustomerInfo | null>(null);
  const [segments, setSegments] = useState<Segment[]>([]);
  const [selectedSegments, setSelectedSegments] = useState<Set<string>>(new Set());

  // Modal States
  const [confirmModal, setConfirmModal] = useState({ isOpen: false, action: '', segmentId: '' });
  const [inputModal, setInputModal] = useState({ isOpen: false, segmentId: '', value: '' });
  const [successMessage, setSuccessMessage] = useState({ isOpen: false, text: '' });
  const [errorMessage, setErrorMessage] = useState({ isOpen: false, text: '' });

  // Loading States
  const [isLoading, setIsLoading] = useState(false);
  const [searchLoading, setSearchLoading] = useState(false);
  const [searchError, setSearchError] = useState('');

  // Calculations
  const totalAmount = segments.reduce((sum, seg) => sum + seg.amount, 0);
  const billStatus = segments.length > 0 ? segments[0].status : 'draft';

  // ============ CUSTOMER SEARCH ============
  const handleCustomerSelect = useCallback((selectedCustomer: CustomerInfo) => {
    setCustomer(selectedCustomer);
    setSegments([]);
    setSelectedSegments(new Set());
  }, []);

  // ============ SEGMENT MANAGEMENT ============
  const handleAddSegment = useCallback((newSegment: Segment) => {
    setSegments(prev => [...prev, newSegment]);
    setSuccessMessage({ isOpen: true, text: 'Segments added successfully!' });
  }, []);

  const handleRemoveSegment = useCallback((segmentId: string) => {
    setSegments(prev => prev.filter(seg => seg.id !== segmentId));
    setSelectedSegments(prev => {
      const updated = new Set(prev);
      updated.delete(segmentId);
      return updated;
    });
    setSuccessMessage({ isOpen: true, text: 'Segment deleted!' });
  }, []);

  const handleSelectSegment = useCallback((segmentId: string, selected: boolean) => {
    setSelectedSegments(prev => {
      const updated = new Set(prev);
      if (selected) {
        updated.add(segmentId);
      } else {
        updated.delete(segmentId);
      }
      return updated;
    });
  }, []);

  const handleSelectAll = useCallback((selected: boolean) => {
    if (selected) {
      const allIds = new Set(segments.map(seg => seg.id || ''));
      setSelectedSegments(allIds);
    } else {
      setSelectedSegments(new Set());
    }
  }, [segments]);

  // ============ BILL ACTIONS ============
  const handleSubmitForReview = async () => {
    if (segments.length === 0) {
      setErrorMessage({ isOpen: true, text: 'Add segments first!' });
      return;
    }

    setConfirmModal({
      isOpen: true,
      action: 'submit',
      segmentId: ''
    });
  };

  const executeSubmitForReview = async () => {
    try {
      setIsLoading(true);
      const response = await api.post('/Employee.php', {
        action: 'submit_bill_for_review',
        account_id: customer?.account_id,
        segments: segments.map(seg => ({
          name: seg.name,
          consumption: seg.consumption,
          amount: seg.amount,
          remarks: seg.remarks
        }))
      });

      if (response.data.success) {
        setSuccessMessage({ isOpen: true, text: 'Bill submitted for review!' });
        setSegments(prev => prev.map(seg => ({ ...seg, status: 'pending_review' })));
        setConfirmModal({ isOpen: false, action: '', segmentId: '' });
      } else {
        setErrorMessage({ isOpen: true, text: response.data.message });
      }
    } catch (err: any) {
      setErrorMessage({ isOpen: true, text: 'Error submitting bill!' });
    } finally {
      setIsLoading(false);
    }
  };

  const handleGenerateBill = () => {
    if (!customer || segments.length === 0) {
      setErrorMessage({ isOpen: true, text: 'No bill to generate!' });
      return;
    }

    // Open bill generation page
    window.open(
      `/NWCBilling/build/pages/generate-bill.php?account_id=${customer.account_id}`,
      '_blank'
    );
  };

  const handleFreezeSelected = async () => {
    if (selectedSegments.size === 0) {
      setErrorMessage({ isOpen: true, text: 'Select segments first!' });
      return;
    }

    setConfirmModal({
      isOpen: true,
      action: 'freeze',
      segmentId: Array.from(selectedSegments)[0]
    });
  };

  const executeFreeze = async () => {
    try {
      setIsLoading(true);
      setSegments(prev =>
        prev.map(seg =>
          selectedSegments.has(seg.id || '') ? { ...seg, status: 'completed' } : seg
        )
      );
      setSuccessMessage({ isOpen: true, text: 'Segments completed!' });
      setConfirmModal({ isOpen: false, action: '', segmentId: '' });
    } catch (err: any) {
      setErrorMessage({ isOpen: true, text: 'Error completing segments!' });
    } finally {
      setIsLoading(false);
    }
  };

  const handleDeleteSelected = async () => {
    if (selectedSegments.size === 0) {
      setErrorMessage({ isOpen: true, text: 'Select segments first!' });
      return;
    }

    setConfirmModal({
      isOpen: true,
      action: 'delete',
      segmentId: Array.from(selectedSegments)[0]
    });
  };

  const executeDelete = async () => {
    try {
      setIsLoading(true);
      setSegments(prev =>
        prev.filter(seg => !selectedSegments.has(seg.id || ''))
      );
      setSelectedSegments(new Set());
      setSuccessMessage({ isOpen: true, text: 'Segments deleted!' });
      setConfirmModal({ isOpen: false, action: '', segmentId: '' });
    } catch (err: any) {
      setErrorMessage({ isOpen: true, text: 'Error deleting segments!' });
    } finally {
      setIsLoading(false);
    }
  };

  const handleReopenSelected = async () => {
    if (selectedSegments.size === 0) {
      setErrorMessage({ isOpen: true, text: 'Select segments first!' });
      return;
    }

    setConfirmModal({
      isOpen: true,
      action: 'reopen',
      segmentId: Array.from(selectedSegments)[0]
    });
  };

  const executeReopen = async () => {
    try {
      setIsLoading(true);
      setSegments(prev =>
        prev.map(seg =>
          selectedSegments.has(seg.id || '') ? { ...seg, status: 'draft' } : seg
        )
      );
      setSuccessMessage({ isOpen: true, text: 'Segments reopened!' });
      setConfirmModal({ isOpen: false, action: '', segmentId: '' });
    } catch (err: any) {
      setErrorMessage({ isOpen: true, text: 'Error reopening segments!' });
    } finally {
      setIsLoading(false);
    }
  };

  const handleAddCorrectionNote = () => {
    if (selectedSegments.size !== 1) {
      setErrorMessage({ isOpen: true, text: 'Select exactly one segment!' });
      return;
    }

    const selectedSegmentId = Array.from(selectedSegments)[0];
    const selectedSegment = segments.find(seg => seg.id === selectedSegmentId);

    setInputModal({
      isOpen: true,
      segmentId: selectedSegmentId,
      value: selectedSegment?.remarks || ''
    });
  };

  const executeSaveNote = async () => {
    try {
      setIsLoading(true);
      setSegments(prev =>
        prev.map(seg =>
          seg.id === inputModal.segmentId
            ? { ...seg, remarks: inputModal.value }
            : seg
        )
      );
      setSuccessMessage({ isOpen: true, text: 'Note saved!' });
      setInputModal({ isOpen: false, segmentId: '', value: '' });
    } catch (err: any) {
      setErrorMessage({ isOpen: true, text: 'Error saving note!' });
    } finally {
      setIsLoading(false);
    }
  };

  const handleUndoNote = () => {
    if (selectedSegments.size !== 1) {
      setErrorMessage({ isOpen: true, text: 'Select exactly one segment!' });
      return;
    }

    const selectedSegmentId = Array.from(selectedSegments)[0];
    setSegments(prev =>
      prev.map(seg =>
        seg.id === selectedSegmentId ? { ...seg, remarks: '' } : seg
      )
    );
    setSuccessMessage({ isOpen: true, text: 'Note removed!' });
  };

  // ============ MODAL HANDLERS ============
  const handleConfirmModal = async () => {
    switch (confirmModal.action) {
      case 'submit':
        await executeSubmitForReview();
        break;
      case 'freeze':
        await executeFreeze();
        break;
      case 'delete':
        await executeDelete();
        break;
      case 'reopen':
        await executeReopen();
        break;
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-4xl font-bold text-gray-900 mb-2">🎉 EMPLOYEE DASHBOARD LOADED! 🎉</h1>
        <p className="text-gray-600 text-xl">If you see this, the dashboard is working!</p>
      </div>
    </div>
  );
};

export default EmployeeDashboard;
