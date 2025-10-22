import { HashRouter, Routes, Route } from "react-router-dom";
import MainApp from "./MainApp";
import NotFound from "./NotFound";
import Products from "./Products";
import Maintenance from "./Maintenance"; // Import Maintenance page

const Index = () => (
  <HashRouter>
    <Routes>
      {/* MainApp handles all tabs internally, so we only need one route for the root path */}
      <Route path="/" element={<MainApp />} />
      {/* We keep other routes for direct access if needed, but MainApp handles the primary navigation */}
      <Route path="/products" element={<Products />} />
      <Route path="/maintenance" element={<Maintenance />} />
      {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
      <Route path="*" element={<NotFound />} />
    </Routes>
  </HashRouter>
);

export default Index;