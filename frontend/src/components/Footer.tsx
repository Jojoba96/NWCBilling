import { Phone, Mail, MapPin, Facebook, Twitter, Instagram, Youtube } from "lucide-react";

const Footer = () => {
  const quickLinks = [
    "عن الشركة",
    "دليل الخدمات",
    "المركز الإعلامي",
    "الوظائف",
    "اتصل بنا",
  ];

  const services = [
    "الفواتير والسداد",
    "إدارة الحساب",
    "طلب توصيل",
    "الشكاوى والاقتراحات",
    "الخدمات الإلكترونية",
  ];

  const socialLinks = [
    { icon: Twitter, label: "Twitter" },
    { icon: Facebook, label: "Facebook" },
    { icon: Instagram, label: "Instagram" },
    { icon: Youtube, label: "Youtube" },
  ];

  return (
    <footer className="gradient-header text-primary-foreground">
      {/* Main Footer */}
      <div className="container mx-auto px-4 py-12 lg:py-16">
        <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
          {/* Company Info */}
          <div>
            <div className="mb-6 flex items-center gap-3">
              <div className="h-12 w-12 rounded-full bg-primary-foreground/20 p-2">
                <div className="h-full w-full rounded-full bg-gradient-to-br from-water-400 to-water-600 flex items-center justify-center">
                  <div className="h-4 w-4 rounded-full bg-primary-foreground/90" />
                </div>
              </div>
              <div>
                <h3 className="font-bold">شركة المياه الوطنية</h3>
                <p className="text-xs opacity-80">National Water Company</p>
              </div>
            </div>
            <p className="mb-4 text-sm text-primary-foreground/80 leading-relaxed">
              نعمل على توفير خدمات مياه موثوقة ومستدامة لجميع المشتركين في المملكة العربية السعودية.
            </p>
            <div className="flex gap-3">
              {socialLinks.map((social, index) => (
                <a
                  key={index}
                  href="#"
                  className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-foreground/10 text-primary-foreground/80 transition-all hover:bg-primary-foreground/20 hover:text-primary-foreground"
                  aria-label={social.label}
                >
                  <social.icon className="h-5 w-5" />
                </a>
              ))}
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <h4 className="mb-4 text-lg font-bold">روابط سريعة</h4>
            <ul className="space-y-2">
              {quickLinks.map((link, index) => (
                <li key={index}>
                  <a
                    href="#"
                    className="text-sm text-primary-foreground/80 transition-colors hover:text-primary-foreground"
                  >
                    {link}
                  </a>
                </li>
              ))}
            </ul>
          </div>

          {/* Services */}
          <div>
            <h4 className="mb-4 text-lg font-bold">خدماتنا</h4>
            <ul className="space-y-2">
              {services.map((service, index) => (
                <li key={index}>
                  <a
                    href="#"
                    className="text-sm text-primary-foreground/80 transition-colors hover:text-primary-foreground"
                  >
                    {service}
                  </a>
                </li>
              ))}
            </ul>
          </div>

          {/* Contact */}
          <div>
            <h4 className="mb-4 text-lg font-bold">تواصل معنا</h4>
            <ul className="space-y-4">
              <li className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-foreground/10">
                  <Phone className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-xs text-primary-foreground/60">مركز الاتصال</p>
                  <p className="font-bold tracking-wider">8004411110</p>
                </div>
              </li>
              <li className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-foreground/10">
                  <Mail className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-xs text-primary-foreground/60">البريد الإلكتروني</p>
                  <p className="text-sm">care@nwc.com.sa</p>
                </div>
              </li>
              <li className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-foreground/10">
                  <MapPin className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-xs text-primary-foreground/60">العنوان</p>
                  <p className="text-sm">الرياض، المملكة العربية السعودية</p>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>

      {/* Bottom Bar */}
      <div className="border-t border-primary-foreground/10">
        <div className="container mx-auto px-4 py-6">
          <div className="flex flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-right">
            <p className="text-sm text-primary-foreground/60">
              © 2024 شركة المياه الوطنية. جميع الحقوق محفوظة.
            </p>
            <div className="flex gap-6">
              <a href="#" className="text-sm text-primary-foreground/60 transition-colors hover:text-primary-foreground">
                سياسة الخصوصية
              </a>
              <a href="#" className="text-sm text-primary-foreground/60 transition-colors hover:text-primary-foreground">
                الشروط والأحكام
              </a>
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
