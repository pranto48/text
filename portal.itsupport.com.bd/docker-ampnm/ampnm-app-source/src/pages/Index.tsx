import { BrowserRouter, Routes, Route } from "react-router-dom";
import MainApp from "./MainApp";
import NotFound from "./NotFound";

const Index = () => (
  <BrowserRouter>
    <Routes>
      <Route path="/" element={<MainApp />} />
      {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
      <Route path="*" element={<NotFound />} />
    </Routes>
  </BrowserRouter>
);

export default Index;