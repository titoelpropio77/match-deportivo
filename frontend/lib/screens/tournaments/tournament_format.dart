import '../../models/tournament_model.dart';

const _months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

String tournamentDate(DateTime date) => '${date.day} ${_months[date.month - 1]} ${date.year}';

String tournamentShortDate(DateTime date) => '${date.day} ${_months[date.month - 1]}';

String tournamentDateTime(DateTime date) =>
    '${tournamentShortDate(date)} ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';

String tournamentFee(TournamentModel tournament) {
  if (tournament.isFree) return 'Gratis';
  final fee = tournament.entryFee;
  return fee == fee.roundToDouble() ? 'Bs ${fee.toInt()}' : 'Bs ${fee.toStringAsFixed(2)}';
}

String bsAmount(double amount) =>
    amount == amount.roundToDouble() ? 'Bs ${amount.toInt()}' : 'Bs ${amount.toStringAsFixed(2)}';

String tournamentPlayers(TournamentModel tournament) => tournament.maxPlayersPerTeam == null
    ? '${tournament.minPlayersPerTeam} o más jugadores'
    : '${tournament.minPlayersPerTeam} a ${tournament.maxPlayersPerTeam} jugadores';
