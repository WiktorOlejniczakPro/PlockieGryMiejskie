package pl.plock.pgm.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilterChip
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pl.plock.pgm.data.GameSummary

/** Lista opublikowanych gier z filtrem kategorii i wyszukiwarką. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GamesScreen(vm: AppViewModel, modifier: Modifier = Modifier) {
    Column(modifier.fillMaxSize()) {
        OutlinedTextField(
            value = vm.search,
            onValueChange = { vm.search = it },
            placeholder = { Text("Szukaj gry…") },
            singleLine = true,
            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
            keyboardActions = KeyboardActions(onSearch = { vm.loadGames() }),
            trailingIcon = { TextButton(onClick = { vm.loadGames() }) { Text("Szukaj") } },
            modifier = Modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, top = 12.dp),
        )

        LazyRow(
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            item {
                FilterChip(
                    selected = vm.selectedCategory == null,
                    onClick = { vm.selectCategory(null) },
                    label = { Text("Wszystkie") },
                )
            }
            items(vm.categories, key = { it.slug }) { category ->
                FilterChip(
                    selected = vm.selectedCategory == category.slug,
                    onClick = { vm.selectCategory(category.slug) },
                    label = { Text(category.name) },
                )
            }
        }

        if (vm.gamesLoading) {
            LinearProgressIndicator(Modifier.fillMaxWidth())
        }

        when {
            vm.gamesError != null && vm.games.isEmpty() -> ErrorBox(vm.gamesError!!, onRetry = { vm.loadGames() })
            !vm.gamesLoading && vm.games.isEmpty() -> Column(
                Modifier.fillMaxSize().padding(32.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text("Brak dostępnych gier", style = MaterialTheme.typography.titleMedium)
                Text(
                    "Nowe gry pojawią się tutaj, gdy organizator je opublikuje.",
                    textAlign = TextAlign.Center,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                TextButton(onClick = { vm.loadGames() }) { Text("Odśwież") }
            }
            else -> LazyColumn(
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                items(vm.games, key = { it.id }) { game ->
                    GameCard(game) { vm.openGame(game.id) }
                }
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun GameCard(game: GameSummary, onClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable(onClick = onClick),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    game.title,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f),
                )
                if (game.isSample) {
                    Tag("PRZYKŁAD", MaterialTheme.colorScheme.tertiaryContainer, MaterialTheme.colorScheme.onTertiaryContainer)
                }
            }
            if (!game.shortDescription.isNullOrBlank()) {
                Text(game.shortDescription, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            FlowRow(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Tag(difficultyLabel(game.difficulty))
                durationLabel(game.estimatedDurationMinutes)?.let { Tag(it) }
                Tag("${game.waypointCount} " + plural(game.waypointCount, "punkt", "punkty", "punktów"))
                if (game.routeLengthM > 0) Tag("ok. ${distanceLabel(game.routeLengthM.toDouble())}")
                Tag("do ${game.maxScore} pkt")
            }
            game.categories?.takeIf { it.isNotEmpty() }?.let { list ->
                Text(
                    list.joinToString(" · ") { it.name },
                    style = MaterialTheme.typography.labelMedium,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
        }
    }
}
