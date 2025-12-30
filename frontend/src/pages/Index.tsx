import Header from "@/components/Header";
import HeroSection from "@/components/HeroSection";
import ServicesSection from "@/components/ServicesSection";
import NewsSection from "@/components/NewsSection";
import Footer from "@/components/Footer";

const Index = () => {
  return (
    <div className="min-h-screen font-cairo">
      <Header />
      <main>
        <HeroSection />
        <ServicesSection />
        <NewsSection />
      </main>
      <Footer />
    </div>
  );
};

export default Index;
