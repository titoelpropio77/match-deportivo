import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/store_model.dart';
import '../../services/store_api_service.dart';
import 'my_orders_screen.dart';
import 'store_detail_screen.dart';
import 'store_widgets.dart';

/// Stores of every sports center, filtered by product category or searched by name.
class StoresScreen extends StatefulWidget {
  const StoresScreen({required this.storeApiService, super.key});

  final StoreApiService storeApiService;

  @override
  State<StoresScreen> createState() => _StoresScreenState();
}

class _StoresScreenState extends State<StoresScreen> {
  final _search = TextEditingController();
  Timer? _debounce;

  List<ProductCategoryModel> _categories = const [];
  int? _categoryId;
  List<StoreModel> _stores = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadCategories();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  /// Only categories some store sells; failures just hide the chips.
  Future<void> _loadCategories() async {
    try {
      final categories = await widget.storeApiService.categories();
      if (!mounted) return;
      setState(() => _categories = categories.where((category) => (category.storesCount ?? 0) > 0).toList());
    } catch (_) {
      if (mounted) setState(() => _categories = const []);
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final stores = await widget.storeApiService.stores(categoryId: _categoryId, search: _search.text);
      if (!mounted) return;
      setState(() {
        _stores = stores;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is StoreApiException ? error.message : 'No pudimos cargar las tiendas.';
        _loading = false;
      });
    }
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), _load);
  }

  void _selectCategory(int? id) {
    setState(() => _categoryId = id);
    _load();
  }

  void _open(StoreModel store) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => StoreDetailScreen(store: store, storeApiService: widget.storeApiService),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tiendas'),
        actions: [
          IconButton(
            tooltip: 'Mis compras',
            icon: const Icon(Icons.receipt_long_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => MyOrdersScreen(storeApiService: widget.storeApiService)),
            ),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            Text(
              'Artículos deportivos, ropa, bebidas y más en las tiendas de los centros deportivos. '
              'Compra en la app y retira en la tienda.',
              style: textTheme.bodyMedium?.copyWith(color: colors.onSurfaceVariant),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _search,
              onChanged: _onSearchChanged,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Buscar tienda',
                prefixIcon: const Icon(Icons.search),
                isDense: true,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(999)),
              ),
            ),
            if (_categories.isNotEmpty) ...[
              const SizedBox(height: 12),
              SizedBox(
                height: 40,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: _categories.length + 1,
                  separatorBuilder: (_, _) => const SizedBox(width: 8),
                  itemBuilder: (context, index) {
                    if (index == 0) {
                      return ChoiceChip(
                        label: const Text('Todas'),
                        selected: _categoryId == null,
                        onSelected: (_) => _selectCategory(null),
                      );
                    }
                    final category = _categories[index - 1];
                    return ChoiceChip(
                      avatar: Icon(productCategoryIcon(category.key), size: 18),
                      label: Text(category.name),
                      selected: _categoryId == category.id,
                      onSelected: (_) => _selectCategory(category.id),
                    );
                  },
                ),
              ),
            ],
            const SizedBox(height: 16),
            if (_loading)
              const Padding(
                padding: EdgeInsets.only(top: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              Column(
                children: [
                  const SizedBox(height: 32),
                  Text(_error!, textAlign: TextAlign.center),
                  TextButton(onPressed: _load, child: const Text('Reintentar')),
                ],
              )
            else if (_stores.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 48),
                child: Column(
                  children: [
                    Icon(Icons.storefront_outlined, size: 48, color: colors.outline),
                    const SizedBox(height: 12),
                    Text(
                      _categoryId == null && _search.text.trim().isEmpty
                          ? 'Todavía no hay tiendas.'
                          : 'No encontramos tiendas con ese filtro.',
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              )
            else
              for (final store in _stores) ...[
                StoreCard(store: store, onTap: () => _open(store)),
                const SizedBox(height: 16),
              ],
          ],
        ),
      ),
    );
  }
}
