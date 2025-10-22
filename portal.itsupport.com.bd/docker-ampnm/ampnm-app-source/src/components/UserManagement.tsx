import { useState, useEffect, useCallback } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Users, UserPlus, Edit, Trash2, RefreshCw } from "lucide-react";
import { showSuccess, showError, showLoading, dismissToast } from "@/utils/toast";
import { getUsers, createUser, updateUserRole, deleteUser, User } from "@/services/networkDeviceService";
import { useCurrentUser } from "@/hooks/useCurrentUser";
import { Skeleton } from "@/components/ui/skeleton";

const UserManagement = () => {
  const [users, setUsers] = useState<User[]>([]);
  const [newUsername, setNewUsername] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [newUserRole, setNewUserRole] = useState<User['role']>('user');
  const [isCreatingUser, setIsCreatingUser] = useState(false);
  const [isLoadingUsers, setIsLoadingUsers] = useState(true);
  const currentUser = useCurrentUser();

  const fetchUsers = useCallback(async () => {
    setIsLoadingUsers(true);
    try {
      const fetchedUsers = await getUsers();
      setUsers(fetchedUsers);
    } catch (error: any) {
      showError(error.message || "Failed to load users.");
    } finally {
      setIsLoadingUsers(false);
    }
  }, []);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  const handleCreateUser = async () => {
    if (!newUsername.trim() || !newPassword.trim()) {
      showError("Username and password are required.");
      return;
    }

    setIsCreatingUser(true);
    const toastId = showLoading("Creating user...");
    try {
      const result = await createUser(newUsername.trim(), newPassword.trim(), newUserRole);
      if (result.success) {
        dismissToast(toastId);
        showSuccess(result.message);
        setNewUsername("");
        setNewPassword("");
        setNewUserRole('user');
        fetchUsers();
      } else {
        throw new Error(result.error || "Failed to create user.");
      }
    } catch (error: any) {
      dismissToast(toastId);
      showError(error.message || "An error occurred while creating the user.");
    } finally {
      setIsCreatingUser(false);
    }
  };

  const handleUpdateUserRole = async (userId: string, currentUsername: string, newRole: User['role']) => {
    if (window.confirm(`Are you sure you want to change the role of user "${currentUsername}" to "${newRole}"?`)) {
      const toastId = showLoading(`Updating role for ${currentUsername}...`);
      try {
        const result = await updateUserRole(userId, newRole);
        if (result.success) {
          dismissToast(toastId);
          showSuccess(result.message);
          fetchUsers();
        } else {
          throw new Error(result.error || "Failed to update user role.");
        }
      } catch (error: any) {
        dismissToast(toastId);
        showError(error.message || "An error occurred while updating the user role.");
      }
    }
  };

  const handleDeleteUser = async (userId: string, username: string) => {
    if (window.confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
      const toastId = showLoading(`Deleting user ${username}...`);
      try {
        const result = await deleteUser(userId);
        if (result.success) {
          dismissToast(toastId);
          showSuccess(result.message);
          fetchUsers();
        } else {
          throw new Error(result.error || "Failed to delete user.");
        }
      } catch (error: any) {
        dismissToast(toastId);
        showError(error.message || "An error occurred while deleting the user.");
      }
    }
  };

  return (
    <div className="space-y-4">
      <Card className="bg-card text-foreground border-border">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-primary">
            <Users className="h-5 w-5" />
            User Management
          </CardTitle>
          <CardDescription>
            Manage application users and their roles (admin/user).
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Create New User Form */}
            <div className="lg:col-span-1">
              <Card className="bg-background border-border">
                <CardHeader>
                  <CardTitle className="text-lg">Create New User</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <Label htmlFor="new-username">Username</Label>
                    <Input
                      id="new-username"
                      value={newUsername}
                      onChange={(e) => setNewUsername(e.target.value)}
                      placeholder="Enter username"
                      className="bg-background border-border text-foreground"
                    />
                  </div>
                  <div>
                    <Label htmlFor="new-password">Password</Label>
                    <Input
                      id="new-password"
                      type="password"
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      placeholder="Enter password"
                      className="bg-background border-border text-foreground"
                    />
                  </div>
                  <div>
                    <Label htmlFor="new-user-role">Role</Label>
                    <Select value={newUserRole} onValueChange={(value: User['role']) => setNewUserRole(value)}>
                      <SelectTrigger id="new-user-role" className="bg-background border-border text-foreground">
                        <SelectValue placeholder="Select role" />
                      </SelectTrigger>
                      <SelectContent className="bg-card text-foreground border-border">
                        <SelectItem value="user" className="hover:bg-secondary">User</SelectItem>
                        <SelectItem value="admin" className="hover:bg-secondary">Admin</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <Button onClick={handleCreateUser} disabled={isCreatingUser} className="w-full bg-primary hover:bg-primary/90 text-primary-foreground">
                    <UserPlus className={`h-4 w-4 mr-2 ${isCreatingUser ? 'animate-spin' : ''}`} />
                    {isCreatingUser ? "Creating..." : "Create User"}
                  </Button>
                </CardContent>
              </Card>
            </div>

            {/* Existing Users List */}
            <div className="lg:col-span-2">
              <Card className="bg-background border-border">
                <CardHeader className="flex flex-row items-center justify-between">
                  <CardTitle className="text-lg">Existing Users</CardTitle>
                  <Button onClick={fetchUsers} variant="outline" size="sm" disabled={isLoadingUsers} className="bg-secondary hover:bg-secondary/80 text-foreground border-border">
                    <RefreshCw className={`h-4 w-4 ${isLoadingUsers ? 'animate-spin' : ''}`} />
                  </Button>
                </CardHeader>
                <CardContent>
                  {isLoadingUsers ? (
                    <div className="text-center p-8">
                      <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-2"></div>
                      <p className="text-sm text-muted-foreground">Loading users...</p>
                    </div>
                  ) : users.length === 0 ? (
                    <div className="text-center p-8 text-muted-foreground">
                      <Users className="h-12 w-12 mx-auto mb-4" />
                      <p>No users found. Create one to get started.</p>
                    </div>
                  ) : (
                    <div className="space-y-3">
                      {users.map((user) => {
                        const isSelf = currentUser && currentUser.username === user.username;
                        return (
                          <div key={user.id} className="flex items-center justify-between p-3 border rounded-lg bg-muted border-border">
                            <div className="flex items-center gap-3">
                              <Users className="h-5 w-5 text-muted-foreground" />
                              <div>
                                <span className="font-medium text-foreground">{user.username} {isSelf && "(You)"}</span>
                                <p className="text-xs text-muted-foreground capitalize">Role: {user.role}</p>
                              </div>
                            </div>
                            <div className="flex items-center gap-2">
                              <Select
                                value={user.role}
                                onValueChange={(value: User['role']) => handleUpdateUserRole(user.id, user.username, value)}
                                disabled={isSelf} // Disable role change for self
                              >
                                <SelectTrigger className="w-[100px] h-8 text-xs bg-background border-border text-foreground">
                                  <SelectValue placeholder="Role" />
                                </SelectTrigger>
                                <SelectContent className="bg-card text-foreground border-border">
                                  <SelectItem value="user" className="hover:bg-secondary">User</SelectItem>
                                  <SelectItem value="admin" className="hover:bg-secondary">Admin</SelectItem>
                                </SelectContent>
                              </Select>
                              <Button
                                variant="destructive"
                                size="icon"
                                className="h-8 w-8"
                                onClick={() => handleDeleteUser(user.id, user.username)}
                                disabled={isSelf} // Disable delete for self
                              >
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default UserManagement;