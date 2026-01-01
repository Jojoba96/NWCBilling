import { useState, useEffect } from "react";
import { LogOut, User, FileText, Home } from "lucide-react";
import { Button } from "@/components/ui/button";
import Header from "@/components/Header";
import Footer from "@/components/Footer";

const Customer = () => {
  const [customerData, setCustomerData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState("overview");

  useEffect(() => {
    // Fetch customer data from session
    fetchCustomerData();
  }, []);

  const fetchCustomerData = async () => {
    try {
      const response = await fetch("/NWCBilling/api/customer-info.php", {
        credentials: "include",
      });
      const data = await response.json();
      if (data.success) {
        setCustomerData(data.user);
      }
    } catch (error) {
      console.error("Error fetching customer data:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = async () => {
    try {
      await fetch("/NWCBilling/api/logout.php", {
        method: "POST",
        credentials: "include",
      });
      window.location.href = "/NWCBilling/app-react/";
    } catch (error) {
      console.error("Logout error:", error);
    }
  };

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
          {/* Top Section */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            {/* Welcome Card */}
            <div className="md:col-span-2 bg-white rounded-2xl shadow-card p-6">
              <h1 className="text-3xl font-bold text-water-600 mb-2">
                أهلا وسهلا {customerData?.full_name}
              </h1>
              <p className="text-gray-600">لوحة تحكم العميل</p>
            </div>

            {/* Quick Actions */}
            <div className="bg-white rounded-2xl shadow-card p-6 flex flex-col justify-center gap-3">
              <Button
                onClick={handleLogout}
                className="w-full bg-red-500 hover:bg-red-600 text-white rounded-lg flex items-center justify-center gap-2"
              >
                <LogOut className="h-4 w-4" />
                تسجيل الخروج
              </Button>
              <Button
                variant="outline"
                className="w-full border-water-500 text-water-500 hover:bg-water-50 rounded-lg flex items-center justify-center gap-2"
              >
                <User className="h-4 w-4" />
                الملف الشخصي
              </Button>
            </div>
          </div>

          {/* Navigation Tabs */}
          <div className="bg-white rounded-2xl shadow-card mb-8">
            <div className="grid grid-cols-3 border-b border-gray-200">
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
              <button
                onClick={() => setActiveTab("accounts")}
                className={`py-4 px-6 font-medium flex items-center justify-center gap-2 transition-colors ${
                  activeTab === "accounts"
                    ? "text-water-600 border-b-2 border-water-600"
                    : "text-gray-600 hover:text-water-600"
                }`}
              >
                <Home className="h-5 w-5" />
                الحسابات
              </button>
            </div>

            {/* Tab Content */}
            <div className="p-6">
              {activeTab === "overview" && (
                <div className="space-y-4">
                  <h2 className="text-xl font-bold text-water-600 text-right">
                    نظرة عامة
                  </h2>
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-water-50 rounded-lg p-4 text-center">
                      <p className="text-sm text-gray-600 mb-2">الحسابات النشطة</p>
                      <p className="text-2xl font-bold text-water-600">0</p>
                    </div>
                    <div className="bg-water-50 rounded-lg p-4 text-center">
                      <p className="text-sm text-gray-600 mb-2">الفواتير المعلقة</p>
                      <p className="text-2xl font-bold text-orange-600">0</p>
                    </div>
                    <div className="bg-water-50 rounded-lg p-4 text-center">
                      <p className="text-sm text-gray-600 mb-2">الفواتير المدفوعة</p>
                      <p className="text-2xl font-bold text-green-600">0</p>
                    </div>
                    <div className="bg-water-50 rounded-lg p-4 text-center">
                      <p className="text-sm text-gray-600 mb-2">إجمالي الاستهلاك</p>
                      <p className="text-2xl font-bold text-water-600">0 م³</p>
                    </div>
                  </div>
                </div>
              )}

              {activeTab === "bills" && (
                <div className="space-y-4">
                  <h2 className="text-xl font-bold text-water-600 text-right">
                    الفواتير
                  </h2>
                  <div className="text-center text-gray-600 py-8">
                    لا توجد فواتير حالياً
                  </div>
                </div>
              )}

              {activeTab === "accounts" && (
                <div className="space-y-4">
                  <h2 className="text-xl font-bold text-water-600 text-right">
                    الحسابات
                  </h2>
                  <div className="text-center text-gray-600 py-8">
                    لا توجد حسابات حالياً
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* User Info Card */}
          <div className="bg-white rounded-2xl shadow-card p-6">
            <h2 className="text-lg font-bold text-water-600 mb-4 text-right">
              معلومات الحساب
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-right">
              <div>
                <p className="text-sm text-gray-600">الاسم الكامل</p>
                <p className="font-medium">{customerData?.full_name}</p>
              </div>
              <div>
                <p className="text-sm text-gray-600">اسم المستخدم</p>
                <p className="font-medium">{customerData?.username}</p>
              </div>
            </div>
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
};

export default Customer;
