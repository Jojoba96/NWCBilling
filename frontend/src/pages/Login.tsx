import { useState } from "react";
import { Eye, EyeOff, IdCard } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import Header from "@/components/Header";
import Footer from "@/components/Footer";

const Login = () => {
  const navigate = useNavigate();
  const [showPassword, setShowPassword] = useState(false);
  const [nationalId, setNationalId] = useState("");
  const [password, setPassword] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setIsLoading(true);

    try {
      const response = await fetch("/NWCBilling/api/login.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          national_id: nationalId.trim(),
          password: password.trim(),
        }),
        credentials: "include",
      });

      const data = await response.json();

      if (data.success) {
        // Redirect to customer dashboard
        navigate("/dashboard");
      } else {
        setError(data.error || "خطأ في تسجيل الدخول");
      }
    } catch (err) {
      setError("حدث خطأ في الاتصال بالخادم");
      console.error(err);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen font-cairo bg-gradient-to-b from-water-50 to-white">
      <Header />
      
      <main className="py-12 px-4">
        <div className="container mx-auto max-w-md">
          {/* Login Card */}
          <div className="bg-white rounded-2xl shadow-card p-8 animate-fade-in">
            {/* Title */}
            <h1 className="text-2xl font-bold text-water-600 text-center mb-8">
              تسجيل الدخول
            </h1>

            <form onSubmit={handleSubmit} className="space-y-6">
              {error && (
                <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm text-right">
                  {error}
                </div>
              )}

              {/* National ID Field */}
              <div className="space-y-2">
                <Label 
                  htmlFor="nationalId" 
                  className="text-water-600 text-sm flex items-center justify-end gap-1"
                >
                  الهوية الوطنية / الإقامة
                  <span className="text-red-500">*</span>
                </Label>
                <div className="relative">
                  <Input
                    id="nationalId"
                    type="text"
                    value={nationalId}
                    onChange={(e) => setNationalId(e.target.value)}
                    placeholder="الهوية الوطنية / الإقامة"
                    className="text-right pr-4 pl-10 h-12 border-2 border-water-300 focus:border-water-500 rounded-lg"
                    dir="rtl"
                  />
                  <IdCard className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-water-400" />
                </div>
              </div>

              {/* Password Field */}
              <div className="space-y-2">
                <Label 
                  htmlFor="password" 
                  className="text-muted-foreground text-sm flex items-center justify-end gap-1"
                >
                  كلمة المرور
                  <span className="text-red-500">*</span>
                </Label>
                <div className="relative">
                  <Input
                    id="password"
                    type={showPassword ? "text" : "password"}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="كلمة المرور"
                    className="text-right pr-4 pl-10 h-12 border-2 border-border focus:border-water-500 rounded-lg"
                    dir="rtl"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute left-3 top-1/2 -translate-y-1/2 text-water-400 hover:text-water-600 transition-colors"
                  >
                    {showPassword ? (
                      <EyeOff className="h-5 w-5" />
                    ) : (
                      <Eye className="h-5 w-5" />
                    )}
                  </button>
                </div>
              </div>

              {/* Login Button */}
              <Button
                type="submit"
                disabled={isLoading}
                className="w-full h-12 bg-water-500 hover:bg-water-600 disabled:bg-water-400 text-white rounded-lg text-base font-medium transition-all hover:shadow-lg disabled:cursor-not-allowed"
              >
                {isLoading ? "جاري التحويل..." : "تسجيل الدخول"}
              </Button>
            </form>
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
};

export default Login;
