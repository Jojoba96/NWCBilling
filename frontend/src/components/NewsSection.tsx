import { ArrowLeft, Calendar } from "lucide-react";
import { Button } from "@/components/ui/button";

const NewsSection = () => {
  const news = [
    {
      date: "25 ديسمبر 2024",
      title: "شركة المياه الوطنية تطلق مبادرة ترشيد الاستهلاك",
      excerpt: "أعلنت شركة المياه الوطنية عن إطلاق مبادرة جديدة لترشيد استهلاك المياه في جميع مناطق المملكة.",
      category: "مبادرات",
    },
    {
      date: "20 ديسمبر 2024",
      title: "تحديثات جديدة على تطبيق المياه الوطنية",
      excerpt: "تم إضافة ميزات جديدة لتحسين تجربة المستخدم وتسهيل الوصول إلى الخدمات الإلكترونية.",
      category: "أخبار",
    },
    {
      date: "15 ديسمبر 2024",
      title: "افتتاح مكتب خدمات جديد في الرياض",
      excerpt: "تم افتتاح مكتب خدمات جديد لتقديم خدمات أفضل للمشتركين في منطقة الرياض.",
      category: "افتتاحات",
    },
  ];

  return (
    <section className="bg-muted/50 py-16 lg:py-24">
      <div className="container mx-auto px-4">
        {/* Section Header */}
        <div className="mb-12 flex flex-col items-center justify-between gap-4 sm:flex-row">
          <h2 className="text-3xl font-bold text-foreground lg:text-4xl">
            آخر الأخبار
          </h2>
          <Button variant="outline" className="group">
            المزيد من الأخبار
            <ArrowLeft className="mr-2 h-4 w-4 transition-transform group-hover:-translate-x-1" />
          </Button>
        </div>

        {/* News Grid */}
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {news.map((item, index) => (
            <article
              key={index}
              className="group overflow-hidden rounded-2xl bg-card shadow-soft hover-lift cursor-pointer"
            >
              {/* Image placeholder */}
              <div className="relative h-48 overflow-hidden bg-gradient-to-br from-water-400 to-water-600">
                <div className="absolute inset-0 bg-gradient-to-t from-water-900/50 to-transparent" />
                <span className="absolute bottom-4 right-4 rounded-full bg-primary-foreground/20 px-3 py-1 text-xs font-medium text-primary-foreground backdrop-blur-sm">
                  {item.category}
                </span>
              </div>

              {/* Content */}
              <div className="p-6">
                <div className="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
                  <Calendar className="h-4 w-4" />
                  <span>{item.date}</span>
                </div>
                <h3 className="mb-3 text-lg font-bold text-foreground line-clamp-2 group-hover:text-primary transition-colors">
                  {item.title}
                </h3>
                <p className="text-muted-foreground line-clamp-2">
                  {item.excerpt}
                </p>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
};

export default NewsSection;
