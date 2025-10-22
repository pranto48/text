const PORTAL_API_URL = 'https://portal.itsupport.com.bd/portal_api.php'; // External Portal API URL

export interface Product {
  id: string;
  name: string;
  description: string;
  price: number;
  max_devices: number;
  license_duration_days: number;
}

const callPortalApi = async (action: string, method: 'GET' | 'POST', params?: Record<string, any>, body?: any) => {
  const options: RequestInit = {
    method: method,
    headers: {
      'Content-Type': 'application/json',
    },
  };
  if (body) {
    options.body = JSON.stringify(body);
  }

  const queryString = params ? `&${new URLSearchParams(params).toString()}` : '';
  const response = await fetch(`${PORTAL_API_URL}?action=${action}${queryString}`, options);
  
  if (!response.ok) {
    const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
  }
  return response.json();
};

export const getProducts = async (): Promise<Product[]> => {
  const data = await callPortalApi('get_products', 'GET');
  if (data.success && Array.isArray(data.products)) {
    return data.products.map((p: any) => ({
      ...p,
      id: String(p.id),
      price: parseFloat(p.price),
      max_devices: parseInt(p.max_devices),
      license_duration_days: parseInt(p.license_duration_days),
    })) as Product[];
  }
  throw new Error("Failed to retrieve product list from portal.");
};