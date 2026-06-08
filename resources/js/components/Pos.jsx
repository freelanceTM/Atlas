import React, { useEffect, useState, useCallback, useRef } from "react";
import axios from "axios";
import toast, { Toaster } from "react-hot-toast";
import Swal from "sweetalert2";
import CreatableSelect from "react-select/creatable";
import SuccessSound from "../sounds/beep-07a.mp3";
import WarningSound from "../sounds/beep-02.mp3";
import playSound from "../utils/playSound";

const BASE = window.location.origin;
const CATS = window.__POS_CATEGORIES__ || [];
const POS_USER = window.__POS_USER__ || "Кассир";

export default function Pos() {
    const [products, setProducts]       = useState([]);
    const [activeCat, setActiveCat]     = useState("all");
    const [carts, setCarts]             = useState([]);
    const [cartTotal, setCartTotal]     = useState(0);
    const [discount, setDiscount]       = useState("");
    const [paid, setPaid]               = useState("");
    const [customerId, setCustomerId]   = useState(null);
    const [customers, setCustomers]     = useState([]);
    const [selectedCustomer, setSelectedCustomer] = useState(null);
    const [orderType, setOrderType]     = useState("takeaway");
    const [search, setSearch]           = useState("");
    const [barcode, setBarcode]         = useState("");
    const [loading, setLoading]         = useState(true);
    const [time, setTime]               = useState(new Date());
    const [addingId, setAddingId]       = useState(null);
    const searchRef  = useRef(null);
    const barcodeRef = useRef(null);
    const barcodeTimer = useRef(null);

    // Clock
    useEffect(() => {
        const t = setInterval(() => setTime(new Date()), 30000);
        return () => clearInterval(t);
    }, []);

    // Load all products (all pages)
    const loadProducts = useCallback(async () => {
        setLoading(true);
        try {
            let page = 1, all = [];
            while (true) {
                const { data } = await axios.get("/admin/get/products", { params: { page } });
                all = [...all, ...data.data];
                if (page >= data.meta.last_page) break;
                page++;
            }
            setProducts(all);
        } catch (e) {
            toast.error("Ошибка загрузки меню");
        } finally {
            setLoading(false);
        }
    }, []);

    // Load cart
    const loadCart = useCallback(async () => {
        try {
            const { data } = await axios.get("/admin/cart");
            setCarts(data.carts || []);
            setCartTotal(parseFloat(data.total) || 0);
        } catch (e) {}
    }, []);

    // Load customers
    const loadCustomers = useCallback(async () => {
        try {
            const { data } = await axios.get("/admin/get/customers");
            const opts = data.map(c => ({ value: c.id, label: c.name }));
            setCustomers(opts);
            const def = opts.find(o => o.label === "Walking Customer" || o.value === 1);
            if (def) { setSelectedCustomer(def); setCustomerId(def.value); }
        } catch (e) {}
    }, []);

    useEffect(() => { loadProducts(); loadCart(); loadCustomers(); }, []);

    // Totals
    const disc    = parseFloat(discount) || 0;
    const paidAmt = parseFloat(paid) || 0;
    const updTotal = Math.max(0, cartTotal - disc);
    const change   = paidAmt - updTotal;
    const totalQty = carts.reduce((s, c) => s + c.quantity, 0);

    // Filtered products
    const filtered = products.filter(p => {
        const matchCat = activeCat === "all" || String(p.category_id) === String(activeCat);
        const matchSearch = !search || p.name.toLowerCase().includes(search.toLowerCase());
        return matchCat && matchSearch;
    });

    // Cart ops
    const addToCart = async (id) => {
        setAddingId(id);
        try {
            await axios.post("/admin/cart", { id });
            await loadCart();
            playSound(SuccessSound);
        } catch (e) {
            playSound(WarningSound);
            toast.error(e?.response?.data?.message || "Недоступно");
        } finally {
            setAddingId(null);
        }
    };

    const increment = async (id) => {
        try { await axios.put("/admin/cart/increment", { id }); await loadCart(); playSound(SuccessSound); }
        catch (e) { toast.error(e?.response?.data?.message || "Ошибка"); }
    };

    const decrement = async (id) => {
        try { await axios.put("/admin/cart/decrement", { id }); await loadCart(); }
        catch (e) { toast.error(e?.response?.data?.message || "Ошибка"); }
    };

    const removeItem = async (id) => {
        try { await axios.put("/admin/cart/delete", { id }); await loadCart(); playSound(WarningSound); }
        catch (e) {}
    };

    const clearCart = async () => {
        if (cartTotal <= 0) return;
        try { await axios.put("/admin/cart/empty"); await loadCart(); setDiscount(""); setPaid(""); toast.success("Корзина очищена"); }
        catch (e) {}
    };

    // Barcode debounce
    useEffect(() => {
        if (!barcode) return;
        clearTimeout(barcodeTimer.current);
        barcodeTimer.current = setTimeout(async () => {
            try {
                const { data } = await axios.get("/admin/get/products", { params: { barcode, page: 1 } });
                if (data.data.length === 1) {
                    await addToCart(data.data[0].id);
                } else {
                    toast.error("Товар не найден");
                }
            } catch (e) {}
            setBarcode("");
        }, 250);
        return () => clearTimeout(barcodeTimer.current);
    }, [barcode]);

    // Checkout
    const checkout = () => {
        if (carts.length === 0) { toast.error("Корзина пуста"); return; }
        Swal.fire({
            title: "Оформить заказ?",
            html: `<div style="font-family:Inter,sans-serif;text-align:left;padding:4px 0">
                <div style="display:flex;justify-content:space-between;margin-bottom:10px">
                    <span style="color:#8b92b8">Тип заказа</span>
                    <strong style="color:#e8eaf8">${orderType === "dine_in" ? "🍽 В зале" : "🥡 На вынос"}</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:10px">
                    <span style="color:#8b92b8">Позиций</span>
                    <strong style="color:#e8eaf8">${totalQty}</strong>
                </div>
                ${disc > 0 ? `<div style="display:flex;justify-content:space-between;margin-bottom:10px">
                    <span style="color:#8b92b8">Скидка</span>
                    <strong style="color:#fbbf24">-${disc.toFixed(2)} ₽</strong>
                </div>` : ""}
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid #252b45;margin-top:4px">
                    <span style="color:#8b92b8;font-size:15px">К оплате</span>
                    <strong style="color:#5b7cfa;font-size:22px">${updTotal.toFixed(2)} ₽</strong>
                </div>
                ${change < 0 ? `<div style="display:flex;justify-content:space-between">
                    <span style="color:#8b92b8">Долг</span>
                    <strong style="color:#ff5470">${Math.abs(change).toFixed(2)} ₽</strong>
                </div>` : change > 0 ? `<div style="display:flex;justify-content:space-between">
                    <span style="color:#8b92b8">Сдача</span>
                    <strong style="color:#20c997">${change.toFixed(2)} ₽</strong>
                </div>` : ""}
            </div>`,
            background: "#13161f",
            color: "#e8eaf8",
            confirmButtonText: "✓ Подтвердить",
            denyButtonText: "Отмена",
            showDenyButton: true,
            confirmButtonColor: "#5b7cfa",
            denyButtonColor: "#252b45",
            customClass: { popup: "swal2-dark-atlas", title: "swal2-atlas-title" },
        }).then(async result => {
            if (!result.isConfirmed) return;
            try {
                const { data } = await axios.put("/admin/order/create", {
                    customer_id: customerId,
                    order_discount: disc,
                    paid: paidAmt,
                    order_type: orderType,
                });
                toast.success("✓ Заказ оформлен!");
                setTimeout(() => { window.location.href = `orders/pos-invoice/${data.order.id}`; }, 600);
            } catch (e) {
                toast.error(e?.response?.data?.message || "Ошибка оформления");
            }
        });
    };

    const createCustomer = async (name) => {
        try {
            const { data } = await axios.post("/admin/create/customers", { name });
            const opt = { value: data.id, label: data.name };
            setCustomers(prev => [opt, ...prev]);
            setSelectedCustomer(opt);
            setCustomerId(data.id);
            toast.success(`Клиент "${name}" создан`);
        } catch (e) { toast.error("Ошибка создания клиента"); }
    };

    const timeStr = time.toLocaleTimeString("ru-RU", { hour: "2-digit", minute: "2-digit" });

    const selectStyles = {
        control: (b, s) => ({ ...b, background: "#191d2b", border: `1.5px solid ${s.isFocused ? "#5b7cfa" : "#252b45"}`, borderRadius: "8px", minHeight: "38px", boxShadow: s.isFocused ? "0 0 0 3px rgba(91,124,250,.15)" : "none", cursor: "pointer" }),
        menu: b => ({ ...b, background: "#191d2b", border: "1px solid #252b45", borderRadius: "10px", boxShadow: "0 8px 30px rgba(0,0,0,.5)", zIndex: 999 }),
        option: (b, s) => ({ ...b, background: s.isFocused ? "#252b45" : "transparent", color: "#e8eaf8", cursor: "pointer", padding: "9px 12px" }),
        singleValue: b => ({ ...b, color: "#e8eaf8", fontSize: "13px" }),
        input: b => ({ ...b, color: "#e8eaf8" }),
        placeholder: b => ({ ...b, color: "#5b6285", fontSize: "13px" }),
        indicatorSeparator: () => ({ display: "none" }),
        dropdownIndicator: b => ({ ...b, color: "#5b6285", padding: "0 8px" }),
        clearIndicator: b => ({ ...b, color: "#5b6285", padding: "0 8px" }),
    };

    return (
        <div className="pos-root">
            <Toaster position="top-center" toastOptions={{ style: { background: "#13161f", color: "#e8eaf8", border: "1px solid #252b45", borderRadius: "10px", fontSize: "13px" }, duration: 1800 }} />

            {/* HEADER */}
            <header className="pos-header">
                <div className="pos-header-left">
                    <a href="/admin" className="pos-back" title="Вернуться в панель">
                        <i className="fas fa-chevron-left"></i>
                    </a>
                    <div className="pos-logo">
                        <div className="pos-logo-icon"><i className="fas fa-utensils"></i></div>
                        <span className="pos-logo-name">OWAZ KAFE</span>
                        <span className="pos-logo-sub">POS</span>
                    </div>
                </div>
                <div className="pos-header-center">
                    <button className={`pos-type-btn${orderType === "takeaway" ? " active" : ""}`} onClick={() => setOrderType("takeaway")}>
                        🥡 На вынос
                    </button>
                    <button className={`pos-type-btn${orderType === "dine_in" ? " active" : ""}`} onClick={() => setOrderType("dine_in")}>
                        🍽 В зале
                    </button>
                </div>
                <div className="pos-header-right">
                    <span className="pos-clock">{timeStr}</span>
                    <span className="pos-cashier"><i className="fas fa-user-circle"></i> {POS_USER}</span>
                </div>
            </header>

            <div className="pos-workspace">
                {/* CATEGORIES */}
                <nav className="pos-cats">
                    <div className="pos-cats-title">Меню</div>
                    <button className={`pos-cat${activeCat === "all" ? " active" : ""}`} onClick={() => setActiveCat("all")}>
                        <span className="pos-cat-emoji">📋</span> Все позиции
                    </button>
                    {CATS.map(c => (
                        <button key={c.id} className={`pos-cat${String(activeCat) === String(c.id) ? " active" : ""}`} onClick={() => setActiveCat(c.id)}>
                            <span className="pos-cat-emoji">🏷</span> {c.name}
                        </button>
                    ))}
                </nav>

                {/* PRODUCTS */}
                <main className="pos-products-panel">
                    <div className="pos-search-bar">
                        <div className="pos-search-wrap">
                            <i className="fas fa-search pos-search-icon"></i>
                            <input ref={searchRef} className="pos-search-input" type="text" placeholder="Поиск по названию..." value={search} onChange={e => setSearch(e.target.value)} autoFocus />
                            {search && <button className="pos-search-clr" onClick={() => { setSearch(""); searchRef.current?.focus(); }}>×</button>}
                        </div>
                        <div className="pos-barcode-wrap">
                            <i className="fas fa-barcode pos-search-icon"></i>
                            <input ref={barcodeRef} className="pos-search-input" type="text" placeholder="Штрихкод..." value={barcode} onChange={e => setBarcode(e.target.value)} />
                        </div>
                    </div>

                    {loading ? (
                        <div className="pos-loader"><div className="pos-spin"></div><p>Загрузка меню...</p></div>
                    ) : (
                        <div className="pos-grid">
                            {filtered.length === 0 ? (
                                <div className="pos-no-results">
                                    <i className="fas fa-search"></i>
                                    <p>Товары не найдены</p>
                                </div>
                            ) : filtered.map(p => (
                                <button
                                    key={p.id}
                                    className={`pos-card${p.quantity <= 0 ? " out-of-stock" : ""}${addingId === p.id ? " adding" : ""}`}
                                    onClick={() => p.quantity > 0 && addToCart(p.id)}
                                    disabled={p.quantity <= 0 || addingId === p.id}
                                >
                                    <div className="pos-card-img">
                                        <img src={`${BASE}/storage/${p.image}`} alt={p.name} onError={e => { e.target.onerror = null; e.target.src = `${BASE}/assets/images/no-image.png`; }} />
                                        {p.quantity <= 0 && <span className="pos-oos-badge">Нет</span>}
                                    </div>
                                    <div className="pos-card-body">
                                        <div className="pos-card-name">{p.name}</div>
                                        <div className="pos-card-price">
                                            {parseFloat(p.discounted_price || p.price).toFixed(0)} ₽
                                            {parseFloat(p.price) > parseFloat(p.discounted_price) && (
                                                <del className="pos-card-old">{parseFloat(p.price).toFixed(0)}</del>
                                            )}
                                        </div>
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}
                </main>

                {/* CART */}
                <aside className="pos-cart-panel">
                    <div className="pos-cart-head">
                        <span><i className="fas fa-receipt"></i> Заказ {totalQty > 0 && <span className="pos-badge">{totalQty}</span>}</span>
                        {carts.length > 0 && (
                            <button className="pos-cart-clr" onClick={clearCart} title="Очистить корзину">
                                <i className="fas fa-trash-alt"></i>
                            </button>
                        )}
                    </div>

                    <div className="pos-cart-items">
                        {carts.length === 0 ? (
                            <div className="pos-cart-empty">
                                <i className="fas fa-shopping-bag"></i>
                                <p>Корзина пуста</p>
                                <small>Выберите товары из меню</small>
                            </div>
                        ) : carts.map(item => (
                            <div key={item.id} className="pos-ci">
                                <div className="pos-ci-name" title={item.product.name}>{item.product.name}</div>
                                <div className="pos-ci-row">
                                    <div className="pos-ci-ctrl">
                                        <button className="pos-ci-minus" onClick={() => decrement(item.id)}>−</button>
                                        <span className="pos-ci-qty">{item.quantity}</span>
                                        <button className="pos-ci-plus" onClick={() => increment(item.id)}>+</button>
                                    </div>
                                    <div className="pos-ci-price">{parseFloat(item.row_total || 0).toFixed(0)} ₽</div>
                                    <button className="pos-ci-del" onClick={() => removeItem(item.id)} title="Удалить">×</button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {carts.length > 0 && (
                        <div className="pos-cart-foot">
                            <div className="pos-totals">
                                <div className="pos-total-row">
                                    <span>Подытог</span>
                                    <span>{cartTotal.toFixed(2)} ₽</span>
                                </div>
                                <div className="pos-total-row">
                                    <span>Скидка</span>
                                    <div className="pos-disc-wrap">
                                        <input className="pos-num-input" type="number" min="0" max={cartTotal} value={discount} onChange={e => { const v = e.target.value; if (!v || parseFloat(v) <= cartTotal) setDiscount(v); }} placeholder="0" />
                                        <span className="pos-curr">₽</span>
                                    </div>
                                </div>
                                <div className="pos-total-row pos-total-main">
                                    <span>К оплате</span>
                                    <strong>{updTotal.toFixed(2)} ₽</strong>
                                </div>
                            </div>

                            <div className="pos-quick">
                                {[5, 10, 20, 50].map(a => (
                                    <button key={a} className="pos-qbtn" onClick={() => setPaid(String(a))}>{a}₽</button>
                                ))}
                                <button className="pos-qbtn pos-qexact" onClick={() => setPaid(updTotal.toFixed(2))}>Точно</button>
                            </div>

                            <div className="pos-paid-row">
                                <span>Оплачено</span>
                                <div className="pos-disc-wrap">
                                    <input className="pos-num-input" type="number" min="0" value={paid} onChange={e => setPaid(e.target.value)} placeholder="0.00" />
                                    <span className="pos-curr">₽</span>
                                </div>
                            </div>

                            {paid !== "" && parseFloat(paid) >= 0 && (
                                <div className={`pos-change${change >= 0 ? " pos-change-ok" : " pos-change-due"}`}>
                                    <span>{change >= 0 ? "Сдача" : "Долг"}</span>
                                    <strong>{Math.abs(change).toFixed(2)} ₽</strong>
                                </div>
                            )}

                            <div className="pos-cust-wrap">
                                <CreatableSelect
                                    options={customers}
                                    value={selectedCustomer}
                                    onChange={opt => { setSelectedCustomer(opt); setCustomerId(opt?.value || null); }}
                                    onCreateOption={createCustomer}
                                    placeholder="👤 Выберите клиента..."
                                    isClearable
                                    styles={selectStyles}
                                    formatCreateLabel={v => `+ Создать клиента "${v}"`}
                                    noOptionsMessage={() => "Не найдено"}
                                />
                            </div>

                            <button className="pos-checkout" onClick={checkout} disabled={carts.length === 0}>
                                <i className="fas fa-check-circle"></i>
                                Оплатить
                                <span className="pos-checkout-amt">{updTotal.toFixed(2)} ₽</span>
                            </button>
                        </div>
                    )}
                </aside>
            </div>
        </div>
    );
}
