import 'dart:async';

import 'package:flutter/material.dart';

import '../../models/court_field_model.dart';
import '../../models/rental_item_model.dart';
import '../../services/court_api_service.dart';
import '../widgets/fake_qr.dart';
import 'reserve_courts_screen.dart';

/// Booking summary and QR payment for every picked range at once. The QR is simulated: the
/// ranges are held as `pending_payment` and "Simular pago" confirms the whole booking.
/// Pops `true` once paid.
class CourtPaymentScreen extends StatefulWidget {
  const CourtPaymentScreen({
    required this.venue,
    required this.items,
    required this.courtApiService,
    this.returnAfterBooking = false,
    super.key,
  });

  /// See [ReserveCourtsScreen.returnAfterBooking].
  final bool returnAfterBooking;

  final CourtVenueModel venue;
  final List<BookingItem> items;
  final CourtApiService courtApiService;

  @override
  State<CourtPaymentScreen> createState() => _CourtPaymentScreenState();
}

class _CourtPaymentScreenState extends State<CourtPaymentScreen> {
  CourtBookingModel? _booking;
  bool _busy = false;
  Timer? _timer;
  Duration _remaining = Duration.zero;

  /// Gear the sports center rents; offered for the ranges of the same sport.
  List<RentalItemModel> _rentalItems = const [];

  /// Picked gear per range: range index → rental item id → quantity.
  final Map<int, Map<int, int>> _quantities = {};

  /// The picked ranges with their rented gear.
  List<BookingItem> get _items => [
        for (var index = 0; index < widget.items.length; index++)
          widget.items[index].withRentals([
            for (final rental in _rentalItems)
              if ((_quantities[index]?[rental.id] ?? 0) > 0)
                RentalSelection(item: rental, quantity: _quantities[index]![rental.id]!),
          ]),
      ];

  double get _amount => _items.fold(0, (total, item) => total + item.amount);
  int get _hours => widget.items.fold(0, (total, item) => total + item.hours);

  @override
  void initState() {
    super.initState();
    _loadRentalItems();
  }

  /// Optional extra: without gear (or on errors) the section just does not show.
  Future<void> _loadRentalItems() async {
    try {
      final items = await widget.courtApiService.rentalItems(widget.venue.id);
      if (mounted) setState(() => _rentalItems = items);
    } catch (_) {}
  }

  void _setQuantity(int rangeIndex, RentalItemModel item, int quantity) {
    setState(() => (_quantities[rangeIndex] ??= {})[item.id] = quantity);
  }

  bool get _expired => _booking?.paymentExpiresAt != null && _remaining <= Duration.zero;

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _generateQr() async {
    setState(() => _busy = true);
    try {
      final booking = await widget.courtApiService.createBooking(_items);
      if (!mounted) return;
      setState(() => _booking = booking);
      _startCountdown();
    } catch (error) {
      _showError(error, 'No pudimos registrar la reserva.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _startCountdown() {
    final expiresAt = _booking?.paymentExpiresAt;
    if (expiresAt == null) return;
    void tick() {
      final remaining = expiresAt.difference(DateTime.now());
      setState(() => _remaining = remaining.isNegative ? Duration.zero : remaining);
      if (remaining <= Duration.zero) _timer?.cancel();
    }

    tick();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  Future<void> _simulatePayment() async {
    final booking = _booking;
    if (booking == null) return;
    setState(() => _busy = true);
    try {
      final paid = await widget.courtApiService.payBooking(booking.code);
      if (!mounted) return;
      _timer?.cancel();
      final goHome = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => CourtReservationSuccessScreen(
            booking: paid,
            venueName: widget.venue.name,
            items: _items,
            doneLabel: widget.returnAfterBooking ? 'Continuar' : 'Volver al inicio',
          ),
        ),
      );
      if (!mounted) return;
      final navigator = Navigator.of(context);
      if (goHome == true && widget.returnAfterBooking) {
        // Back to the screen that opened the booking flow, telling it a booking was made.
        navigator.popUntil((route) => route.settings.name == ReserveCourtsScreen.routeName || route.isFirst);
        if (navigator.canPop()) navigator.pop(true);
        return;
      }
      // The success screen only closes itself: popping several routes from there left this screen
      // popping again while closing, which removed the home route too (blank screen).
      if (goHome == true) {
        navigator.popUntil((route) => route.isFirst);
      } else {
        navigator.pop(true);
      }
    } catch (error) {
      _showError(error, 'No pudimos confirmar el pago.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Leaving with an unpaid booking releases its hours.
  Future<void> _leave() async {
    final booking = _booking;
    if (booking == null || _expired) {
      Navigator.of(context).pop();
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('¿Cancelar la reserva?'),
        content: const Text('Todavía no pagaste. Si sales, los horarios quedarán libres para otros.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Seguir pagando'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Cancelar reserva'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await widget.courtApiService.cancelBooking(booking.code);
    } catch (_) {
      // The hold expires on its own after the payment window.
    }
    if (mounted) Navigator.of(context).pop();
  }

  void _showError(Object error, String fallback) {
    if (!mounted) return;
    final message = error is CourtApiException ? error.message : fallback;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final booking = _booking;
    final textTheme = Theme.of(context).textTheme;
    return PopScope(
      canPop: booking == null || _expired,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _leave();
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('Detalle de la reserva')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(widget.venue.name, style: textTheme.titleMedium),
                    Text(widget.venue.address, style: textTheme.bodySmall),
                    const SizedBox(height: 8),
                    for (final item in _items) _BookingItemRow(item: item),
                    const Divider(),
                    _Row(label: 'Horas', value: '$_hours'),
                    _Row(label: 'Total', value: formatBs(_amount), emphasize: true),
                  ],
                ),
              ),
            ),
            if (booking == null && _rentalItems.isNotEmpty)
              _RentalsSection(
                ranges: widget.items,
                rentalItems: _rentalItems,
                quantities: _quantities,
                onChanged: _setQuantity,
              ),
            const SizedBox(height: 16),
            if (booking == null)
              FilledButton.icon(
                onPressed: _busy ? null : _generateQr,
                icon: _busy ? const _ButtonSpinner() : const Icon(Icons.qr_code_2),
                label: Text('Pagar con QR · ${formatBs(_amount)}'),
              )
            else
              _QrPanel(
                reference: booking.code,
                amount: booking.amount,
                remaining: _remaining,
                expired: _expired,
                busy: _busy,
                onSimulatePayment: _simulatePayment,
                onCancel: _leave,
              ),
          ],
        ),
      ),
    );
  }
}

/// "Cancha 1 · Fútbol 5 / jueves 2 de octubre · 09:00–11:00 (2 h) · Bs 100".
class _BookingItemRow extends StatelessWidget {
  const _BookingItemRow({required this.item});

  final BookingItem item;

  @override
  Widget build(BuildContext context) {
    final row = Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${item.field.name} · ${item.sport.name}',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                Text(
                  '${formatReservationDate(item.date)} · ${item.startTime}–${item.endTime} '
                  '(${item.hours} h)',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          Text(formatBs(item.courtAmount), style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
    if (item.rentals.isEmpty) return row;

    final small = Theme.of(context).textTheme.bodySmall;
    return Column(
      children: [
        row,
        for (final rental in item.rentals)
          Padding(
            padding: const EdgeInsets.only(left: 12, bottom: 4),
            child: Row(
              children: [
                Icon(Icons.add, size: 14, color: Theme.of(context).colorScheme.onSurfaceVariant),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    '${rental.quantity} × ${rental.item.name}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: small,
                  ),
                ),
                Text(formatBs(rental.item.amountFor(rental.quantity, item.hours)), style: small),
              ],
            ),
          ),
      ],
    );
  }
}

/// "¿Necesitas algo más?": gear of the center for the sport of each picked range, with quantities.
class _RentalsSection extends StatelessWidget {
  const _RentalsSection({
    required this.ranges,
    required this.rentalItems,
    required this.quantities,
    required this.onChanged,
  });

  final List<BookingItem> ranges;
  final List<RentalItemModel> rentalItems;
  final Map<int, Map<int, int>> quantities;
  final void Function(int rangeIndex, RentalItemModel item, int quantity) onChanged;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final colors = Theme.of(context).colorScheme;
    final offers = [
      for (var index = 0; index < ranges.length; index++)
        (index, rentalItems.where((item) => item.sportId == ranges[index].sport.id).toList()),
    ].where((offer) => offer.$2.isNotEmpty).toList();
    if (offers.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 8, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('¿Necesitas algo más?', style: textTheme.titleMedium),
              Text(
                'El centro te alquila el equipo; se suma al total de la reserva.',
                style: textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
              ),
              for (final (index, items) in offers) ...[
                if (ranges.length > 1)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(
                      '${ranges[index].field.name} · ${ranges[index].sport.name} · '
                      '${ranges[index].startTime}–${ranges[index].endTime}',
                      style: textTheme.labelLarge,
                    ),
                  ),
                for (final item in items)
                  _RentalRow(
                    item: item,
                    hours: ranges[index].hours,
                    quantity: quantities[index]?[item.id] ?? 0,
                    onChanged: (quantity) => onChanged(index, item, quantity),
                  ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _RentalRow extends StatelessWidget {
  const _RentalRow({required this.item, required this.hours, required this.quantity, required this.onChanged});

  static const _maxPerRange = 20;

  final RentalItemModel item;
  final int hours;
  final int quantity;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final max = item.stock == null || item.stock! > _maxPerRange ? _maxPerRange : item.stock!;
    final price = item.isFlat ? '${formatBs(item.price)} por reserva' : '${formatBs(item.price)} / hora';
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: textTheme.bodyMedium),
                Text(
                  quantity > 0 ? '$price · ${formatBs(item.amountFor(quantity, hours))}' : price,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.onSurfaceVariant),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Quitar ${item.name}',
            visualDensity: VisualDensity.compact,
            onPressed: quantity > 0 ? () => onChanged(quantity - 1) : null,
            icon: const Icon(Icons.remove_circle_outline),
          ),
          SizedBox(
            width: 24,
            child: Text('$quantity', textAlign: TextAlign.center, style: textTheme.titleSmall),
          ),
          IconButton(
            tooltip: 'Agregar ${item.name}',
            visualDensity: VisualDensity.compact,
            onPressed: quantity < max ? () => onChanged(quantity + 1) : null,
            icon: const Icon(Icons.add_circle_outline),
          ),
        ],
      ),
    );
  }
}

class _QrPanel extends StatelessWidget {
  const _QrPanel({
    required this.reference,
    required this.amount,
    required this.remaining,
    required this.expired,
    required this.busy,
    required this.onSimulatePayment,
    required this.onCancel,
  });

  final String reference;
  final double amount;
  final Duration remaining;
  final bool expired;
  final bool busy;
  final VoidCallback onSimulatePayment;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final minutes = remaining.inMinutes.toString().padLeft(2, '0');
    final seconds = (remaining.inSeconds % 60).toString().padLeft(2, '0');

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Text('Escanea el QR para pagar', style: textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(formatBs(amount), style: textTheme.headlineMedium),
            const SizedBox(height: 12),
            Opacity(
              opacity: expired ? 0.2 : 1,
              child: Container(
                padding: const EdgeInsets.all(12),
                color: Colors.white,
                child: SizedBox.square(
                  dimension: 200,
                  child: CustomPaint(painter: FakeQrPainter('$reference|$amount')),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Text('Referencia: $reference', style: textTheme.bodySmall),
            const SizedBox(height: 4),
            Text(
              expired
                  ? 'El QR expiró y los horarios fueron liberados.'
                  : 'Tus horarios quedan apartados por $minutes:$seconds',
              style: textTheme.bodyMedium?.copyWith(
                color: expired ? Theme.of(context).colorScheme.error : null,
              ),
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.secondaryContainer,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Text(
                'Modo prueba: el pago por QR todavía no está conectado al banco. '
                'Usa "Simular pago" para confirmar la reserva.',
                textAlign: TextAlign.center,
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: busy || expired ? null : onSimulatePayment,
                icon: busy ? const _ButtonSpinner() : const Icon(Icons.check_circle_outline),
                label: const Text('Simular pago'),
              ),
            ),
            if (!expired)
              TextButton(onPressed: busy ? null : onCancel, child: const Text('Cancelar reserva')),
          ],
        ),
      ),
    );
  }
}

class CourtReservationSuccessScreen extends StatelessWidget {
  const CourtReservationSuccessScreen({
    required this.booking,
    required this.venueName,
    required this.items,
    this.doneLabel = 'Volver al inicio',
    super.key,
  });

  final String doneLabel;

  final CourtBookingModel booking;
  final String venueName;
  final List<BookingItem> items;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Reserva confirmada'), automaticallyImplyLeading: false),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Icon(Icons.check_circle, size: 72, color: Colors.green.shade600),
          const SizedBox(height: 12),
          Text(
            items.length == 1 ? '¡Cancha reservada!' : '¡Canchas reservadas!',
            textAlign: TextAlign.center,
            style: textTheme.headlineSmall,
          ),
          const SizedBox(height: 4),
          Text('Pago recibido · ${booking.code}', textAlign: TextAlign.center),
          const SizedBox(height: 24),
          Text(venueName, style: textTheme.titleMedium),
          for (final item in items) _BookingItemRow(item: item),
          const Divider(),
          _Row(label: 'Total pagado', value: formatBs(booking.amount), emphasize: true),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(doneLabel),
          ),
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value, this.emphasize = false});

  final String label;
  final String value;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(child: Text(label)),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: emphasize ? 18 : null,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ButtonSpinner extends StatelessWidget {
  const _ButtonSpinner();

  @override
  Widget build(BuildContext context) {
    return const SizedBox(
      width: 18,
      height: 18,
      child: CircularProgressIndicator(strokeWidth: 2),
    );
  }
}


String formatReservationDate(DateTime date) {
  const days = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
  const months = [
    'enero',
    'febrero',
    'marzo',
    'abril',
    'mayo',
    'junio',
    'julio',
    'agosto',
    'septiembre',
    'octubre',
    'noviembre',
    'diciembre',
  ];
  return '${days[date.weekday - 1]} ${date.day} de ${months[date.month - 1]}';
}
