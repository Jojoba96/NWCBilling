import { FileText, Droplets, Wallet, Wrench, Users, Building2 } from "lucide-react";

const ServicesSection = () => {
  const services = [
    {
      icon: FileText,
      title: "الفواتير",
      description: "استعراض ودفع الفواتير إلكترونياً",
    },
    {
      icon: Droplets,
      title: "الاستهلاك",
      description: "متابعة استهلاك المياه بشكل يومي",
    },
    {
      icon: Wallet,
      title: "السداد",
      description: "طرق دفع متعددة وآمنة",
    },
    {
      icon: Wrench,
      title: "الصيانة",
      description: "طلب خدمات الصيانة والإصلاح",
    },
    {
      icon: Users,
      title: "خدمة العملاء",
      description: "دعم فني على مدار الساعة",
    },
    {
      icon: Building2,
      title: "العقارات",
      description: "إدارة عقاراتك المتعددة",
    },
  ];

  return (
    <section className="bg-background py-16 lg:py-24">
      <div className="container mx-auto px-4">
        {/* Section Header */}
        <div className="mb-12 text-center">
          <h2 className="mb-4 text-3xl font-bold text-foreground lg:text-4xl">
            خدماتنا الإلكترونية
          </h2>
          <p className="mx-auto max-w-2xl text-lg text-muted-foreground">
            نوفر لك مجموعة متكاملة من الخدمات الإلكترونية لتسهيل إدارة حسابك
          </p>
        </div>

        {/* Services Grid */}
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {services.map((service, index) => (
            <div
              key={index}
              className="group gradient-card rounded-2xl p-6 shadow-soft hover-lift cursor-pointer"
              style={{ animationDelay: `${index * 0.1}s` }}
            >
              <div className="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary transition-all group-hover:bg-primary group-hover:text-primary-foreground group-hover:shadow-glow">
                <service.icon className="h-7 w-7" />
              </div>
              <h3 className="mb-2 text-xl font-bold text-foreground">
                {service.title}
              </h3>
              <p className="text-muted-foreground">
                {service.description}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};

export default ServicesSection;
