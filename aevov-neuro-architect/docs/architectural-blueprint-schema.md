# Architectural Blueprint Schema for Aevov Neuro-Architect

This document defines the JSON schema for the architectural blueprint used by the Aevov Neuro-Architect to compose new models, memory systems, and reasoning pipelines. The blueprint is a declarative specification of the desired system's structure and properties.

## Top-Level Blueprint Structure

```json
{
    "name": "string",
    "description": "string",
    "layers": [],
    "memory": {},
    "reasoning": {},
    "training": {}
}
```

### Properties

*   `name` (string, required): A human-readable name for the architectural blueprint.
*   `description` (string, optional): A brief description of the blueprint's purpose or characteristics.
*   `layers` (array, optional): Defines the computational layers of the model. See [Layer Object Schema](#layer-object-schema) below.
*   `memory` (object, optional): Defines the memory system components. See [Memory Object Schema](#memory-object-schema) below.
*   `reasoning` (object, optional): Defines the reasoning engine configuration. See [Reasoning Object Schema](#reasoning-object-schema) below.
*   `training` (object, optional): Defines parameters for training the composed model. See [Training Object Schema](#training-object-schema) below.

---

## Layer Object Schema

Each object in the `layers` array represents a computational layer within the model.

```json
{
    "type": "string",
    "pattern_type": "string",
    "count": "integer",
    "options": "object"
}
```

### Properties

*   `type` (string, required): The type of the computational layer.
    *   Examples: `"input"`, `"hidden"`, `"output"`, `"convolutional"`, `"recurrent"`, `"attention"`.
*   `pattern_type` (string, required): The type of neural pattern associated with this layer. This refers to patterns stored in the `NeuralPatternCatalog`.
    *   Examples: `"text_embedding"`, `"image_feature"`, `"audio_chunk"`, `"symbolic_rule"`.
*   `count` (integer, required): The number of patterns of `pattern_type` to instantiate or select for this layer.
*   `options` (object, optional): A flexible object for layer-specific configuration.
    *   Examples: `{"activation": "relu"}`, `{"kernel_size": 3}`, `{"num_heads": 8}`.

---

## Memory Object Schema

The `memory` object defines the components and configuration of the memory system.

```json
{
    "enabled": "boolean",
    "components": [],
    "access_policy": "string"
}
```

### Properties

*   `enabled` (boolean, required): Whether a memory system should be composed for this blueprint.
*   `components` (array, required if `enabled` is true): An array of memory component objects. See [Memory Component Object Schema](#memory-component-object-schema) below.
*   `access_policy` (string, optional): Defines who can access this memory system.
    *   Examples: `"public"`, `"private"`, `"restricted"`. Default: `"private"`.

---

### Memory Component Object Schema

Each object in the `memory.components` array represents a distinct memory unit (Astrocyte).

```json
{
    "type": "string",
    "capacity": "integer",
    "decay_rate": "number",
    "connections": [],
    "options": "object"
}
```

### Properties

*   `type` (string, required): The type of memory component.
    *   Examples: `"short_term"`, `"long_term"`, `"semantic"`, `"episodic"`.
*   `capacity` (integer, required): The storage capacity of this memory component (e.g., in MB, number of patterns, or abstract units).
*   `decay_rate` (number, required): A value between 0.0 and 1.0 representing how quickly memories in this component decay.
*   `connections` (array, optional): Defines connections to other layers or memory components.
    *   Each connection object: `{"target_type": "layer" | "memory", "target_index": "integer", "connection_type": "string"}`.
        *   `target_type`: `"layer"` or `"memory"`.
        *   `target_index`: The index of the target layer or memory component in their respective arrays.
        *   `connection_type`: Examples: `"feedforward"`, `"feedback"`, `"associative"`.
*   `options` (object, optional): A flexible object for component-specific configuration.
    *   Examples: `{"persistence_level": "high"}`, `{"indexing_method": "vector_similarity"}`.

---

## Reasoning Object Schema

The `reasoning` object defines the configuration for the reasoning engine.

```json
{
    "enabled": "boolean",
    "type": "string",
    "parameters": "object",
    "connections": []
}
```

### Properties

*   `enabled` (boolean, required): Whether a reasoning engine should be integrated with this blueprint.
*   `type` (string, required if `enabled` is true): The type of reasoning engine to use.
    *   Examples: `"analogy"`, `"deductive"`, `"inductive"`, `"hrm"` (Hierarchical Reasoning Model).
*   `parameters` (object, optional): Parameters specific to the chosen reasoning type.
    *   Examples for `"hrm"`: `{"high_level_cycles": 5, "low_level_timesteps": 10}`.
    *   Examples for `"analogy"`: `{"similarity_threshold": 0.8, "top_k_analogies": 5}`.
*   `connections` (array, optional): Defines connections to computational layers or memory components.
    *   Each connection object: `{"target_type": "layer" | "memory", "target_index": "integer", "connection_type": "string"}`.
        *   `target_type`: `"layer"` or `"memory"`.
        *   `target_index`: The index of the target layer or memory component.
        *   `connection_type`: Examples: `"input"`, `"output"`, `"control"`.

---

## Training Object Schema

The `training` object defines parameters for training the composed model.

```json
{
    "epochs": "integer",
    "learning_rate": "number",
    "dataset_id": "string",
    "optimizer": "string",
    "loss_function": "string"
}
```

### Properties

*   `epochs` (integer, required): The number of training epochs.
*   `learning_rate` (number, required): The learning rate for the optimizer.
*   `dataset_id` (string, required): The ID of the dataset to use for training.
*   `optimizer` (string, optional): The optimization algorithm to use (e.g., `"adam"`, `"sgd"`).
*   `loss_function` (string, optional): The loss function to use (e.g., `"mse"`, `"cross_entropy"`).