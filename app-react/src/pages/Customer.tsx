import { useState, useEffect } from "react";
import { FileText, Home, DollarSign, Droplet } from "lucide-react";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useAuth } from "@/context/AuthContext";

interface Bill {
  id: number;
  bill_date: string;
  due_date: string;
  total_amount: number;
  status: string;
  segments?: any[];
}

const Customer = () => {
  const { auth } = useAuth();
  const [bills, setBills] = useState<Bill[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState("overview");

  useEffect(() => {
    if (auth?.account_id) {
      fetchCustomerBills();
    }
  }, [auth]);

  const fetchCustomerBills = async () => {
    try {
      setLoading(true);
      if (!auth?.account_id) return;
      
      const response = await fetch('/NWCBilling/api/customer-bills.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          account_id: auth.account_id,
        }),
      });
      
      const data = await response.json();
      if (data.success && Array.isArray(data.bills)) {
        // Convert string amounts to numbers
        const processedBills = data.bills.map((bill: any) => ({
          ...bill,
          total_amount: parseFloat(bill.total_amount) || 0,
          segments: (bill.segments || []).map((seg: any) => ({
            ...seg,
            consumption: parseFloat(seg.consumption) || 0,
            amount: parseFloat(seg.amount) || 0,
          })),
        }));
        setBills(processedBills);
      }
    } catch (error) {
      console.error('Error fetching bills:', error);
    } finally {
      setLoading(false);
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending_review':
        return 'bg-yellow-100 text-yellow-800';
      case 'approved':
        return 'bg-green-100 text-green-800';
      case 'rejected':
        return 'bg-red-100 text-red-800';
      case 'paid':
        return 'bg-blue-100 text-blue-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusLabel = (status: string) => {
    switch (status) {
      case 'pending_review':
        return 'قيد المراجعة';
      case 'approved':
        return 'موافق عليه';
      case 'rejected':
        return 'مرفوض';
      case 'paid':
        return 'مدفوع';
      default:
        return status;
    }
  };

  // Show only ACTIVE bills (what customer needs to pay)
  const totalAmount = bills
    .filter(b => b.status === 'active')
    .reduce((sum, bill) => sum + (bill.total_amount || 0), 0);
  // Calculate only ACTIVE bills (what customer needs to pay)
  const pendingAmount = bills
    .filter(b => b.status === 'active')
    .reduce((sum, bill) => sum + (bill.total_amount || 0), 0);
  const paidAmount = bills
    .filter(b => b.status === 'paid')
    .reduce((sum, bill) => sum + (bill.total_amount || 0), 0);

  if (loading) {
    return (
      <div className="min-h-screen font-cairo bg-gradient-to-b from-water-50 to-white flex items-center justify-center">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-water-600"></div>
          <p className="mt-4 text-water-600">جاري التحميل...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen font-cairo bg-gradient-to-b from-water-50 to-white">
      <Header />

      <main className="py-8 px-4">
        <div className="container mx-auto max-w-6xl">
          {/* Welcome Section */}
          <div className="bg-gradient-to-r from-water-500 to-water-600 rounded-2xl p-8 mb-8 text-white">
            <h1 className="text-3xl font-bold mb-2">أهلا وسهلا {auth?.full_name}</h1>
            <p className="text-water-100">رقم الحساب: {auth?.account_number}</p>
            <p className="text-water-100">النوع: {auth?.account_type === 'residential' ? 'سكني' : 'تجاري'}</p>
          </div>

          {/* Statistics Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div className="bg-white rounded-2xl shadow-card p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 mb-1">إجمالي الفواتير</p>
                  <p className="text-2xl font-bold text-water-600">{bills.length}</p>
                </div>
                <FileText className="h-8 w-8 text-water-500 opacity-20" />
              </div>
            </div>

            <div className="bg-white rounded-2xl shadow-card p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 mb-1">المبلغ الإجمالي</p>
                  <p className="text-2xl font-bold text-water-600">{totalAmount.toFixed(2)} ر.س</p>
                </div>
                <DollarSign className="h-8 w-8 text-water-500 opacity-20" />
              </div>
            </div>

            <div className="bg-white rounded-2xl shadow-card p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 mb-1">المبالغ المعلقة</p>
                  <p className="text-2xl font-bold text-orange-600">{pendingAmount.toFixed(2)} ر.س</p>
                </div>
                <DollarSign className="h-8 w-8 text-orange-500 opacity-20" />
              </div>
            </div>

            <div className="bg-white rounded-2xl shadow-card p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 mb-1">المبالغ المدفوعة</p>
                  <p className="text-2xl font-bold text-green-600">{paidAmount.toFixed(2)} ر.س</p>
                </div>
                <Droplet className="h-8 w-8 text-green-500 opacity-20" />
              </div>
            </div>
          </div>

          {/* Navigation Tabs */}
          <div className="bg-white rounded-2xl shadow-card mb-8">
            <div className="grid grid-cols-2 border-b border-gray-200">
              <button
                onClick={() => setActiveTab("overview")}
                className={`py-4 px-6 font-medium flex items-center justify-center gap-2 transition-colors ${
                  activeTab === "overview"
                    ? "text-water-600 border-b-2 border-water-600"
                    : "text-gray-600 hover:text-water-600"
                }`}
              >
                <Home className="h-5 w-5" />
                نظرة عامة
              </button>
              <button
                onClick={() => setActiveTab("bills")}
                className={`py-4 px-6 font-medium flex items-center justify-center gap-2 transition-colors ${
                  activeTab === "bills"
                    ? "text-water-600 border-b-2 border-water-600"
                    : "text-gray-600 hover:text-water-600"
                }`}
              >
                <FileText className="h-5 w-5" />
                الفواتير
              </button>
            </div>

            {/* Tab Content */}
            <div className="p-6">
              {activeTab === "overview" && (
                <div className="space-y-4">
                  <h2 className="text-xl font-bold text-water-600 text-right mb-6">
                    ملخص الحساب
                  </h2>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-right">
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">الاسم الكامل</p>
                      <p className="font-medium text-lg">{auth?.full_name}</p>
                    </div>
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">رقم الحساب</p>
                      <p className="font-medium text-lg">{auth?.account_number}</p>
                    </div>
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">رقم الهاتف</p>
                      <p className="font-medium text-lg">{auth?.phone_number}</p>
                    </div>
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">البريد الإلكتروني</p>
                      <p className="font-medium text-lg">{auth?.email}</p>
                    </div>
                    <div className="space-y-2">
                      <p className="text-sm text-gray-600">نوع الحساب</p>
                      <p className="font-medium text-lg">
                        {auth?.account_type === 'residential' ? 'سكني' : 'تجاري'}
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {activeTab === "bills" && (
                <div className="space-y-4">
                  <h2 className="text-xl font-bold text-water-600 text-right mb-6">
                    الفواتير
                  </h2>
                  {bills.length === 0 ? (
                    <div className="text-center text-gray-600 py-12">
                      لا توجد فواتير حالياً
                    </div>
                  ) : (
                    <div className="space-y-3">
                      {bills.map((bill) => (
                        <div
                          key={bill.id}
                          className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
                        >
                          <div className="flex items-center justify-between flex-wrap gap-4">
                            <div className="flex-1 min-w-0">
                              <p className="text-sm text-gray-600 mb-1">
                                تاريخ الفاتورة: {bill.bill_date}
                              </p>
                              <p className="text-sm text-gray-600">
                                تاريخ الاستحقاق: {bill.due_date}
                              </p>
                            </div>
                            <div className="text-right">
                              <p className="text-lg font-bold text-water-600">
                                {bill.total_amount.toFixed(2)} ر.س
                              </p>
                              <span className={`inline-block px-3 py-1 rounded-full text-sm font-medium mt-2 ${getStatusColor(bill.status)}`}>
                                {getStatusLabel(bill.status)}
                              </span>
                            </div>
                          </div>
                          {bill.segments && bill.segments.length > 0 && (
                            <div className="mt-3 pt-3 border-t border-gray-200">
                              <p className="text-sm font-medium text-gray-700 mb-2">البنود:</p>
                              <div className="space-y-1">
                                {bill.segments.map((segment, idx) => (
                                  <p key={idx} className="text-sm text-gray-600">
                                    {segment.name}: {segment.consumption} م³ - {segment.amount} ر.س
                                  </p>
                                ))}
                              </div>
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
};

export default Customer;
