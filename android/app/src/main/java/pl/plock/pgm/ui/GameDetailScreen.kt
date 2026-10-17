package pl.plock.pgm.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import pl.plock.pgm.data.RankingEntry

/** Opis gry, mapa trasy, ranking i przycisk „Rozpocznij grę”. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
fun GameDetailScreen(vm: AppViewModel, gameId: Int, modifier: Modifier = Modifier) {
    val game = vm.game
    if (game == null || game.id != gameId) {
        if (vm.loading) LoadingBox(modifier) else ErrorBox("Nie udało się wczytać gry.", onRetry = { vm.openGame(gameId) }, modifier)
        return
    }

    val playing = vm.profile?.history?.any { it.gameId == gameId && it.status == "in_progress" } == true

    Column(
        modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        OsmMap(
            points = game.waypoints.map { MapPoint(it.id, it.position, it.latitude, it.longitude, PointState.TODO) },
            modifier = Modifier.fillMaxWidth().height(220.dp).clip(RoundedCornerShape(16.dp)),
        )

        FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Tag(difficultyLabel(game.difficulty))
            durationLabel(game.estimatedDurationMinutes)?.let { Tag(it) }
            Tag("${game.waypointCount} " + plural(game.waypointCount, "punkt", "punkty", "punktów"))
            if (game.routeLengthM > 0) Tag("ok. ${distanceLabel(game.routeLengthM.toDouble())} w linii prostej")
            Tag("do ${game.maxScore} pkt")
        }

        if (!game.description.isNullOrBlank()) {
            Text(game.description, style = MaterialTheme.typography.bodyLarge)
        } else if (!game.shortDescription.isNullOrBlank()) {
            Text(game.shortDescription, style = MaterialTheme.typography.bodyLarge)
        }

        Button(
            onClick = { vm.requestStart(gameId) },
            enabled = !vm.busy && game.waypoints.isNotEmpty(),
            modifier = Modifier.fillMaxWidth().height(52.dp),
        ) {
            if (vm.busy) {
                CircularProgressIndicator(Modifier.size(20.dp), color = MaterialTheme.colorScheme.onPrimary, strokeWidth = 2.dp)
            } else {
                Text(if (playing) "Kontynuuj grę" else "Rozpocznij grę", fontWeight = FontWeight.Bold)
            }
        }
        Text(
            "Za ukończenie całej trasy dostaniesz dodatkowo ${game.completionPoints} pkt.",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        HorizontalDivider()
        Text("Trasa", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        for (wp in game.waypoints) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                Tag(wp.position.toString(), MaterialTheme.colorScheme.primary, MaterialTheme.colorScheme.onPrimary)
                Column(Modifier.weight(1f)) {
                    Text(wp.name, fontWeight = FontWeight.SemiBold)
                    wp.task?.let {
                        Text(
                            "${taskTypeLabel(it.taskType)} · ${it.maxPoints} pkt",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }
        }

        HorizontalDivider()
        Text("Najlepsi gracze", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        RankingList(vm.gameRanking, vm.player?.id, empty = "Nikt jeszcze nie ukończył tej gry. Bądź pierwszy!")
    }
}

/** Lista miejsc w rankingu; wyróżnia bieżącego gracza. */
@Composable
fun RankingList(entries: List<RankingEntry>, currentPlayerId: Int?, empty: String) {
    if (entries.isEmpty()) {
        Text(empty, color = MaterialTheme.colorScheme.onSurfaceVariant)
        return
    }
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        for (entry in entries) {
            val me = entry.playerId == currentPlayerId
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(
                    when (entry.rank) { 1 -> "🥇"; 2 -> "🥈"; 3 -> "🥉"; else -> "${entry.rank}." },
                    modifier = Modifier.padding(end = 4.dp),
                )
                Column(Modifier.weight(1f)) {
                    Text(
                        entry.nickname + if (me) " (Ty)" else "",
                        fontWeight = if (me) FontWeight.Bold else FontWeight.Normal,
                        color = if (me) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface,
                    )
                    entry.gamesCompleted?.let {
                        Text("ukończone gry: $it", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
                Text("${entry.score} pkt", fontWeight = FontWeight.Bold)
            }
        }
    }
}
