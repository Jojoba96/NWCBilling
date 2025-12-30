import { Phone, Search, Globe, ChevronDown, Menu, X } from "lucide-react";
import { useState } from "react";

const Header = () => {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  const navItems = [
    { label: "عن الشركة", hasDropdown: true },
    { label: "دليل الخدمات", hasDropdown: true },
    { label: "مبادرات التحول الوطني", hasDropdown: false },
    { label: "المركز الإعلامي", hasDropdown: false },
  ];

  return (
    <header className="sticky top-0 z-50 w-full">
      {/* Top Bar */}
      <div className="gradient-header">
        <div className="container mx-auto px-4 py-3">
          <div className="flex items-center justify-between">
            {/* Logo */}
            <div className="flex items-center gap-3">
              <div className="relative h-14 w-14 rounded-full bg-primary-foreground/20 p-2 backdrop-blur-sm">
                <div className="h-full w-full rounded-full bg-gradient-to-br from-water-400 to-water-600 flex items-center justify-center">
                  <div className="h-6 w-6 rounded-full bg-primary-foreground/90" />
                </div>
              </div>
              <div className="hidden sm:block text-primary-foreground">
                <h1 className="text-lg font-bold leading-tight">شركة المياه الوطنية</h1>
                <p className="text-xs opacity-80">National Water Company</p>
              </div>
            </div>

            {/* Desktop Actions */}
            <div className="hidden md:flex items-center gap-6">
              <button className="flex items-center gap-2 text-primary-foreground/90 hover:text-primary-foreground transition-colors">
                <Search className="h-5 w-5" />
              </button>
              
              <div className="flex items-center gap-2 text-primary-foreground">
                <span className="text-sm font-medium">مركز الإتصال الموحد</span>
                <span className="font-bold text-lg tracking-wider">8004411110</span>
                <Phone className="h-5 w-5" />
              </div>

              <button className="flex items-center gap-2 rounded-full border border-primary-foreground/30 px-4 py-2 text-primary-foreground hover:bg-primary-foreground/10 transition-all">
                <Globe className="h-4 w-4" />
                <span className="text-sm font-medium">English</span>
              </button>
            </div>

            {/* Mobile Menu Button */}
            <button 
              className="md:hidden text-primary-foreground p-2"
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
            >
              {isMobileMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
            </button>
          </div>
        </div>

        {/* Navigation */}
        <nav className="border-t border-primary-foreground/10">
          <div className="container mx-auto px-4">
            <div className="hidden md:flex items-center justify-between py-3">
              {/* Main Nav */}
              <ul className="flex items-center gap-8">
                {navItems.map((item, index) => (
                  <li key={index}>
                    <button className="flex items-center gap-1 text-primary-foreground/90 hover:text-primary-foreground transition-colors font-medium">
                      {item.label}
                      {item.hasDropdown && <ChevronDown className="h-4 w-4" />}
                    </button>
                  </li>
                ))}
              </ul>

              {/* Auth Actions */}
              <div className="flex items-center gap-4">
                <button className="text-primary-foreground/90 hover:text-primary-foreground transition-colors font-medium">
                  تسجيل الدخول
                </button>
                <span className="text-primary-foreground/30">|</span>
                <button className="text-primary-foreground/90 hover:text-primary-foreground transition-colors font-medium">
                  علاقات الموردين
                </button>
              </div>
            </div>
          </div>
        </nav>
      </div>

      {/* Mobile Menu */}
      {isMobileMenuOpen && (
        <div className="md:hidden bg-primary animate-fade-in">
          <div className="container mx-auto px-4 py-4 space-y-4">
            {navItems.map((item, index) => (
              <button 
                key={index}
                className="block w-full text-right text-primary-foreground/90 hover:text-primary-foreground py-2 font-medium"
              >
                {item.label}
              </button>
            ))}
            <div className="pt-4 border-t border-primary-foreground/10 space-y-2">
              <button className="block w-full text-right text-primary-foreground/90 hover:text-primary-foreground py-2 font-medium">
                تسجيل الدخول
              </button>
              <button className="block w-full text-right text-primary-foreground/90 hover:text-primary-foreground py-2 font-medium">
                علاقات الموردين
              </button>
            </div>
          </div>
        </div>
      )}
    </header>
  );
};

export default Header;
