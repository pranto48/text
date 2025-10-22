import { useState, useEffect, useCallback } from "react";
import { User } from "@/services/networkDeviceService";
import { showError } from "@/utils/toast";

interface CurrentUser extends User {
  user_id: string;
}

export const useCurrentUser = () => {
  const [currentUser, setCurrentUser] = useState<CurrentUser | null>(null);

  const fetchCurrentUser = useCallback(async () => {
    try {
      const response = await fetch('/api.php?action=get_user_info');
      if (!response.ok) throw new Error("Failed to fetch current user info.");
      const data = await response.json();
      
      // Ensure user_id is present and map it to id for consistency
      if (data.user_id) {
        setCurrentUser({
          id: String(data.user_id),
          user_id: String(data.user_id),
          username: data.username,
          role: data.role,
          created_at: '', // Placeholder
        });
      } else {
        // This should only happen if the user is logged out, but the component is rendered (which shouldn't happen)
        setCurrentUser(null);
      }
    } catch (error) {
      console.error("Error fetching current user:", error);
      // Do not show error toast here as it might spam if API is down, rely on MainApp error handling
    }
  }, []);

  useEffect(() => {
    fetchCurrentUser();
  }, [fetchCurrentUser]);

  return currentUser;
};