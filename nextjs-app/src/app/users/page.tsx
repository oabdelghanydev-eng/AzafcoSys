'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import {
    Users,
    Search,
    Plus,
    Shield,
    ShieldCheck,
    ShieldAlert,
    Lock,
    Unlock,
    MoreHorizontal,
    Loader2,
    Mail,
    Trash2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { LoadingState } from '@/components/shared/loading-state';
import { ErrorState } from '@/components/shared/error-state';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { PermissionsEditor } from '@/components/users/permissions-editor';
import { PermissionGate } from '@/components/shared/permission-gate';
import { cn } from '@/lib/utils';
import {
    useUsers,
    useCreateUser,
    useDeleteUser,
    useLockUser,
    useUnlockUser,
    type User,
} from '@/hooks/api/use-users';

// Fallback component for unauthorized access
function UnauthorizedAccess() {
    return (
        <div className="flex flex-col items-center justify-center min-h-[400px] text-center">
            <ShieldAlert className="h-16 w-16 text-destructive mb-4" />
            <h2 className="text-2xl font-bold mb-2">Access Denied</h2>
            <p className="text-muted-foreground max-w-md">
                You do not have permission to manage users.
                Please contact an administrator if you believe this is an error.
            </p>
        </div>
    );
}

function CreateUserDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [isAdmin, setIsAdmin] = useState(false);
    const createUser = useCreateUser();

    const handleSubmit = async () => {
        if (!name || !email) {
            toast.error('Name and email are required');
            return;
        }

        try {
            await createUser.mutateAsync({
                name,
                email,
                password: password || undefined,
                is_admin: isAdmin,
            });
            toast.success('User created successfully');
            onOpenChange(false);
            setName('');
            setEmail('');
            setPassword('');
            setIsAdmin(false);
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to create user');
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Plus className="h-5 w-5" />
                        Add New User
                    </DialogTitle>
                    <DialogDescription>
                        Enter the new user details
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4 pt-4">
                    <div className="space-y-2">
                        <Label>Full Name *</Label>
                        <Input
                            placeholder="Enter name..."
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Email Address *</Label>
                        <Input
                            type="email"
                            placeholder="user@example.com"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Password (optional)</Label>
                        <Input
                            type="password"
                            placeholder="Leave blank for no password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                        />
                    </div>
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div>
                            <Label>Admin User</Label>
                            <p className="text-sm text-muted-foreground">
                                Full access to all features
                            </p>
                        </div>
                        <Switch checked={isAdmin} onCheckedChange={setIsAdmin} />
                    </div>
                    <div className="flex justify-end gap-2 pt-4">
                        <Button variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleSubmit} disabled={createUser.isPending}>
                            {createUser.isPending && (
                                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                            )}
                            Create User
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function UserCard({ user, onManagePermissions }: { user: User; onManagePermissions: () => void }) {
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const lockUser = useLockUser();
    const unlockUser = useUnlockUser();
    const deleteUser = useDeleteUser();

    const handleLock = async () => {
        try {
            await lockUser.mutateAsync(user.id);
            toast.success('Account locked');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to lock account');
        }
    };

    const handleUnlock = async () => {
        try {
            await unlockUser.mutateAsync(user.id);
            toast.success('Account unlocked');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to unlock account');
        }
    };

    const handleDelete = async () => {
        try {
            await deleteUser.mutateAsync(user.id);
            toast.success('User deleted');
        } catch (err) {
            const error = err as Error;
            toast.error(error.message || 'Failed to delete user');
        }
    };

    return (
        <>
            <div className={cn(
                "flex items-center justify-between p-4 border rounded-lg transition-colors",
                user.is_locked && "bg-red-50 border-red-200"
            )}>
                <div className="flex items-center gap-4">
                    {/* Avatar */}
                    <div className={cn(
                        "h-12 w-12 rounded-full flex items-center justify-center text-white font-bold text-lg",
                        user.is_admin ? "bg-purple-500" : "bg-blue-500"
                    )}>
                        {user.name.charAt(0).toUpperCase()}
                    </div>

                    {/* Info */}
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 className="font-semibold">{user.name}</h3>
                            {user.is_admin && (
                                <Badge variant="secondary" className="bg-purple-100 text-purple-700">
                                    <ShieldCheck className="h-3 w-3 mr-1" />
                                    Admin
                                </Badge>
                            )}
                            {user.is_locked && (
                                <Badge variant="destructive">
                                    <Lock className="h-3 w-3 mr-1" />
                                    Locked
                                </Badge>
                            )}
                        </div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Mail className="h-3 w-3" />
                            {user.email}
                        </div>
                        <p className="text-xs text-muted-foreground mt-1">
                            {user.is_admin ? 'Full permissions' : `${user.permissions?.length || 0} permissions`}
                        </p>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex items-center gap-2">
                    {!user.is_admin && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={onManagePermissions}
                        >
                            <Shield className="h-4 w-4 mr-1" />
                            Permissions
                        </Button>
                    )}

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {user.is_locked ? (
                                <DropdownMenuItem onClick={handleUnlock}>
                                    <Unlock className="h-4 w-4 mr-2" />
                                    Unlock Account
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem onClick={handleLock}>
                                    <Lock className="h-4 w-4 mr-2" />
                                    Lock Account
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => setShowDeleteConfirm(true)}
                                className="text-red-600"
                            >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete User
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <ConfirmDialog
                open={showDeleteConfirm}
                onOpenChange={setShowDeleteConfirm}
                title="Delete User"
                description={`Are you sure you want to delete "${user.name}"? This action cannot be undone.`}
                confirmLabel="Delete"
                onConfirm={handleDelete}
                loading={deleteUser.isPending}
                variant="destructive"
            />
        </>
    );
}

export default function UsersPage() {
    return (
        <PermissionGate permission="admin.users" fallback={<UnauthorizedAccess />}>
            <UsersContent />
        </PermissionGate>
    );
}

function UsersContent() {
    const [search, setSearch] = useState('');
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [showPermissionsEditor, setShowPermissionsEditor] = useState(false);

    const { data: users, isLoading, error, refetch } = useUsers(
        search ? { search } : undefined
    );

    const handleManagePermissions = (user: User) => {
        setSelectedUser(user);
        setShowPermissionsEditor(true);
    };

    if (isLoading) {
        return <LoadingState message="Loading users..." />;
    }

    if (error) {
        return (
            <ErrorState
                title="Failed to load users"
                message="Could not fetch user data"
                retry={() => refetch()}
            />
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold flex items-center gap-2">
                        <Users className="h-6 w-6" />
                        User Management
                    </h1>
                    <p className="text-muted-foreground">
                        Add and manage users and permissions
                    </p>
                </div>
                <Button onClick={() => setShowCreateDialog(true)}>
                    <Plus className="h-4 w-4 mr-2" />
                    Add User
                </Button>
            </div>

            {/* Search */}
            <div className="relative max-w-md">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    placeholder="Search users..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="pl-10"
                />
            </div>

            {/* Stats */}
            <div className="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center gap-4">
                            <div className="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                                <Users className="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">{users?.length || 0}</p>
                                <p className="text-sm text-muted-foreground">Total Users</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center gap-4">
                            <div className="h-12 w-12 rounded-full bg-purple-100 flex items-center justify-center">
                                <ShieldCheck className="h-6 w-6 text-purple-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">
                                    {users?.filter(u => u.is_admin).length || 0}
                                </p>
                                <p className="text-sm text-muted-foreground">Admins</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center gap-4">
                            <div className="h-12 w-12 rounded-full bg-red-100 flex items-center justify-center">
                                <Lock className="h-6 w-6 text-red-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">
                                    {users?.filter(u => u.is_locked).length || 0}
                                </p>
                                <p className="text-sm text-muted-foreground">Locked Accounts</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Users List */}
            <Card>
                <CardHeader>
                    <CardTitle>Users</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {!users || users.length === 0 ? (
                        <p className="text-center text-muted-foreground py-8">
                            No users found
                        </p>
                    ) : (
                        users.map((user) => (
                            <UserCard
                                key={user.id}
                                user={user}
                                onManagePermissions={() => handleManagePermissions(user)}
                            />
                        ))
                    )}
                </CardContent>
            </Card>

            {/* Dialogs */}
            <CreateUserDialog
                open={showCreateDialog}
                onOpenChange={setShowCreateDialog}
            />

            {selectedUser && (
                <PermissionsEditor
                    userId={selectedUser.id}
                    userName={selectedUser.name}
                    currentPermissions={selectedUser.permissions || []}
                    open={showPermissionsEditor}
                    onOpenChange={setShowPermissionsEditor}
                />
            )}
        </div>
    );
}
