'use client';

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { Customer, Invoice, Collection, Shipment, Product, Supplier } from '@/types';

// Types for API responses
interface ApiListResponse<T> {
    data?: T[];
}

interface CustomerStatement {
    invoices: Invoice[];
    collections: Collection[];
}

interface StockItem {
    product_id: number;
    product_name: string;
    available: number;
}

// Customers
export function useCustomers() {
    return useQuery({
        queryKey: ['customers'],
        queryFn: async () => (await api.getCustomers()) as Customer[] | ApiListResponse<Customer>,
    });
}

export function useCustomer(id: number) {
    return useQuery<Customer>({
        queryKey: ['customer', id],
        queryFn: () => api.getCustomer(id) as Promise<Customer>,
        enabled: !!id,
    });
}

export function useCustomerStatement(id: number) {
    return useQuery<CustomerStatement>({
        queryKey: ['customer-statement', id],
        queryFn: () => api.getCustomerStatement(id) as Promise<CustomerStatement>,
        enabled: !!id,
    });
}

// Products
export function useProducts() {
    return useQuery({
        queryKey: ['products'],
        queryFn: async () => (await api.getProducts()) as Product[] | ApiListResponse<Product>,
    });
}

// Suppliers
export function useSuppliers() {
    return useQuery({
        queryKey: ['suppliers'],
        queryFn: async () => (await api.getSuppliers()) as Supplier[] | ApiListResponse<Supplier>,
    });
}

// Invoices
export function useInvoices(params?: Record<string, string>) {
    return useQuery({
        queryKey: ['invoices', params],
        queryFn: async () => (await api.getInvoices(params)) as Invoice[] | ApiListResponse<Invoice>,
    });
}

export function useCreateInvoice() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: api.createInvoice,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['invoices'] });
            queryClient.invalidateQueries({ queryKey: ['customers'] });
            queryClient.invalidateQueries({ queryKey: ['products'] });
            queryClient.invalidateQueries({ queryKey: ['stock'] });
        },
    });
}

export function useCancelInvoice() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => api.cancelInvoice(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['invoices'] });
            queryClient.invalidateQueries({ queryKey: ['customers'] });
            queryClient.invalidateQueries({ queryKey: ['stock'] });
        },
    });
}

// Collections
export function useCollections(params?: Record<string, string>) {
    return useQuery({
        queryKey: ['collections', params],
        queryFn: async () => (await api.getCollections(params)) as Collection[] | ApiListResponse<Collection>,
    });
}

export function useCreateCollection() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: api.createCollection,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['collections'] });
            queryClient.invalidateQueries({ queryKey: ['customers'] });
            queryClient.invalidateQueries({ queryKey: ['invoices'] });
        },
    });
}

// Shipments
export function useShipments(params?: Record<string, string>) {
    return useQuery({
        queryKey: ['shipments', params],
        queryFn: async () => (await api.getShipments(params)) as Shipment[] | ApiListResponse<Shipment>,
    });
}

export function useCreateShipment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: api.createShipment,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['shipments'] });
            queryClient.invalidateQueries({ queryKey: ['products'] });
            queryClient.invalidateQueries({ queryKey: ['stock'] });
        },
    });
}

// Stock
export function useStock() {
    return useQuery<StockItem[]>({
        queryKey: ['stock'],
        queryFn: () => api.getStock() as Promise<StockItem[]>,
    });
}

// Dashboard
export function useDashboard() {
    return useQuery({
        queryKey: ['dashboard'],
        queryFn: () => api.getDashboard(),
    });
}

