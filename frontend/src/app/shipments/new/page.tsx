'use client';

import { useRouter } from 'next/navigation';
import { useForm, useFieldArray } from 'react-hook-form';
import { useAuth } from '@/hooks/useAuth';
import { useSuppliers, useProducts, useCreateShipment } from '@/hooks/useApi';
import { formatCurrency, safeMultiply, getTodayISO } from '@/lib/format';
import { extractData } from '@/lib/helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';

interface ShipmentItemFormData {
    product_id: string;
    cartons: number;
    weight_per_unit: number;
    initial_quantity: number;
    unit_cost: number;
}

interface ShipmentFormData {
    supplier_id: string;
    date: string;
    items: ShipmentItemFormData[];
}

export default function NewShipmentPage() {
    const router = useRouter();
    const { user, loading: authLoading } = useAuth();
    const { data: suppliers = [] } = useSuppliers();
    const { data: products = [] } = useProducts();
    const createShipment = useCreateShipment();

    const {
        register,
        handleSubmit,
        control,
        watch,
        setValue,
        formState: { errors },
    } = useForm<ShipmentFormData>({
        defaultValues: {
            supplier_id: '',
            date: getTodayISO(),
            items: [{ product_id: '', cartons: 1, weight_per_unit: 0, initial_quantity: 0, unit_cost: 0 }],
        },
    });

    const { fields, append, remove } = useFieldArray({
        control,
        name: 'items',
    });

    const watchItems = watch('items');

    if (authLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="text-xl">جاري التحميل...</div>
            </div>
        );
    }

    if (!user) {
        router.push('/login');
        return null;
    }

    const supplierList = extractData(suppliers);
    const supplierOptions = supplierList.map((s) => ({
        value: String(s.id),
        label: s.name,
    }));

    const productList = extractData(products);
    const productOptions = productList.map((p) => ({
        value: String(p.id),
        label: p.name,
    }));

    // Calculate total cost
    const totalCost = watchItems.reduce(
        (sum, item) => sum + safeMultiply(item.initial_quantity || 0, item.unit_cost || 0),
        0
    );

    const onSubmit = async (data: ShipmentFormData) => {
        try {
            await createShipment.mutateAsync({
                supplier_id: parseInt(data.supplier_id),
                date: data.date,
                items: data.items.map(item => ({
                    product_id: parseInt(item.product_id),
                    cartons: item.cartons,
                    weight_per_unit: item.weight_per_unit,
                    initial_quantity: item.initial_quantity,
                    unit_cost: item.unit_cost,
                })),
            });
            router.push('/shipments');
        } catch (error: unknown) {
            const message = error instanceof Error ? error.message : 'حدث خطأ';
            alert(message);
        }
    };

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white shadow-sm">
                <div className="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">شحنة جديدة</h1>
                    <Button variant="outline" onClick={() => router.push('/shipments')}>
                        رجوع
                    </Button>
                </div>
            </header>

            <main className="max-w-5xl mx-auto px-4 py-8">
                <form onSubmit={handleSubmit(onSubmit)}>
                    {/* Supplier & Date */}
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>بيانات الشحنة</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>المورد</Label>
                                <Combobox
                                    options={supplierOptions}
                                    value={watch('supplier_id')}
                                    onChange={(val) => setValue('supplier_id', val)}
                                    placeholder="اختر المورد..."
                                    searchPlaceholder="ابحث عن مورد..."
                                />
                                {errors.supplier_id && (
                                    <p className="text-red-500 text-sm mt-1">{errors.supplier_id.message}</p>
                                )}
                            </div>
                            <div>
                                <Label>التاريخ</Label>
                                <Input type="date" {...register('date')} />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Items */}
                    <Card className="mb-6">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>الأصناف</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => append({ product_id: '', cartons: 1, weight_per_unit: 0, initial_quantity: 0, unit_cost: 0 })}
                            >
                                + إضافة صنف
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {fields.map((field, index) => (
                                <div key={field.id} className="grid grid-cols-12 gap-3 mb-4 items-end">
                                    <div className="col-span-3">
                                        <Label>الصنف</Label>
                                        <Combobox
                                            options={productOptions}
                                            value={watchItems[index]?.product_id || ''}
                                            onChange={(val) => setValue(`items.${index}.product_id`, val)}
                                            placeholder="اختر الصنف..."
                                            searchPlaceholder="ابحث..."
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <Label>عدد الكراتين</Label>
                                        <Input
                                            type="number"
                                            {...register(`items.${index}.cartons`, { valueAsNumber: true })}
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <Label>الوزن/وحدة (كجم)</Label>
                                        <Input
                                            type="number"
                                            step="0.001"
                                            {...register(`items.${index}.weight_per_unit`, { valueAsNumber: true })}
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <Label>الكمية (كجم)</Label>
                                        <Input
                                            type="number"
                                            step="0.001"
                                            {...register(`items.${index}.initial_quantity`, { valueAsNumber: true })}
                                        />
                                    </div>
                                    <div className="col-span-2">
                                        <Label>التكلفة/كجم</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            {...register(`items.${index}.unit_cost`, { valueAsNumber: true })}
                                        />
                                    </div>
                                    <div className="col-span-1">
                                        {fields.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => remove(index)}
                                            >
                                                حذف
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {errors.items && (
                                <p className="text-red-500 text-sm">{errors.items.message}</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Total */}
                    <Card className="mb-6">
                        <CardContent className="pt-6">
                            <div className="flex justify-between items-center">
                                <span className="text-lg">إجمالي التكلفة:</span>
                                <span className="text-2xl font-bold text-blue-600">
                                    {formatCurrency(totalCost)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit */}
                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" onClick={() => router.push('/shipments')}>
                            إلغاء
                        </Button>
                        <Button type="submit" disabled={createShipment.isPending}>
                            {createShipment.isPending ? 'جاري الحفظ...' : 'حفظ الشحنة'}
                        </Button>
                    </div>
                </form>
            </main>
        </div>
    );
}
