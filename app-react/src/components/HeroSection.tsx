import { ArrowLeft, Smartphone, Shield, Droplets, FileText } from "lucide-react";
import { Button } from "@/components/ui/button";

const HeroSection = () => {
  const features = [
    { icon: Droplets, label: "إدارة الاستهلاك" },
    { icon: FileText, label: "الفواتير الإلكترونية" },
    { icon: Shield, label: "خدمات آمنة" },
  ];

  return (
    <section className="relative min-h-[600px] lg:min-h-[700px] overflow-hidden">
      {/* Background with gradient overlay */}
      <div className="absolute inset-0 gradient-hero" />
      
      {/* Decorative elements */}
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-40 -right-40 h-80 w-80 rounded-full bg-water-400/20 blur-3xl animate-float" />
        <div className="absolute bottom-20 -left-20 h-60 w-60 rounded-full bg-water-300/20 blur-3xl animate-float-delayed" />
        <div className="absolute top-1/2 left-1/4 h-40 w-40 rounded-full bg-primary-foreground/5 blur-2xl" />
      </div>

      {/* Shimmer effect */}
      <div className="absolute inset-0 shimmer opacity-30" />

      <div className="container relative mx-auto px-4 py-16 lg:py-24">
        <div className="grid items-center gap-12 lg:grid-cols-2">
          {/* Content */}
          <div className="text-center lg:text-right animate-slide-up">
            <div className="mb-6 inline-block">
              <span className="inline-flex items-center gap-2 rounded-full bg-primary-foreground/10 px-4 py-2 text-sm font-medium text-primary-foreground backdrop-blur-sm">
                <Smartphone className="h-4 w-4" />
                تطبيق المياه الوطنية
              </span>
            </div>
            
            <h2 className="mb-4 text-4xl font-bold leading-tight text-primary-foreground md:text-5xl lg:text-6xl">
              أكثر من <span className="text-water-200">35</span> خدمة لعقارك
            </h2>
            
            <p className="mb-8 text-xl text-primary-foreground/80 md:text-2xl">
              في تطبيق المياه الوطنية
            </p>

            {/* Features */}
            <div className="mb-8 flex flex-wrap justify-center gap-4 lg:justify-start">
              {features.map((feature, index) => (
                <div 
                  key={index}
                  className="flex items-center gap-2 rounded-lg bg-primary-foreground/10 px-4 py-2 backdrop-blur-sm"
                  style={{ animationDelay: `${index * 0.1}s` }}
                >
                  <feature.icon className="h-5 w-5 text-water-200" />
                  <span className="text-sm font-medium text-primary-foreground">{feature.label}</span>
                </div>
              ))}
            </div>

            <Button 
              size="lg" 
              variant="hero"
              className="group"
            >
              المزيد
              <ArrowLeft className="mr-2 h-5 w-5 transition-transform group-hover:-translate-x-1" />
            </Button>
          </div>

          {/* Phone Mockup */}
          <div className="relative flex justify-center lg:justify-start">
            <div className="relative animate-float">
              {/* Phone frame */}
              <div className="relative h-[500px] w-[280px] rounded-[3rem] border-4 border-primary-foreground/20 bg-gradient-to-b from-water-800 to-water-900 p-3 shadow-2xl backdrop-blur-sm">
                {/* Screen */}
                <div className="h-full w-full overflow-hidden rounded-[2.5rem] bg-gradient-to-b from-water-100 to-water-50">
                  {/* Status bar */}
                  <div className="flex items-center justify-between px-6 py-3 text-xs text-water-700">
                    <span>9:41</span>
                    <div className="flex items-center gap-1">
                      <div className="h-2 w-2 rounded-full bg-water-500" />
                      <div className="h-2 w-4 rounded-full bg-water-500" />
                    </div>
                  </div>
                  
                  {/* App header */}
                  <div className="gradient-header px-4 py-6 text-center text-primary-foreground">
                    <div className="mx-auto mb-2 h-12 w-12 rounded-full bg-primary-foreground/20 flex items-center justify-center">
                      <Droplets className="h-6 w-6" />
                    </div>
                    <p className="text-sm font-bold">المياه الوطنية</p>
                  </div>

                  {/* App content */}
                  <div className="p-4 space-y-3">
                    {/* Balance card */}
                    <div className="rounded-xl bg-gradient-to-br from-water-500 to-water-600 p-4 text-primary-foreground shadow-lg">
                      <p className="text-xs opacity-80">الرصيد الحالي</p>
                      <p className="text-2xl font-bold">92.66 <span className="text-sm">ر.س</span></p>
                    </div>

                    {/* Quick actions */}
                    <div className="grid grid-cols-3 gap-2">
                      {["الفواتير", "الاستهلاك", "الدعم"].map((label, i) => (
                        <div key={i} className="rounded-lg bg-water-100 p-3 text-center shadow-sm">
                          <div className="mx-auto mb-1 h-8 w-8 rounded-full bg-water-200 flex items-center justify-center">
                            <div className="h-3 w-3 rounded-full bg-water-500" />
                          </div>
                          <p className="text-xs text-water-700">{label}</p>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </div>

              {/* Glow effect */}
              <div className="absolute -inset-4 -z-10 rounded-[4rem] bg-water-400/20 blur-2xl" />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default HeroSection;
