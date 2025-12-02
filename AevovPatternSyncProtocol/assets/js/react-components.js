import React, { useState, useEffect } from 'react';
import { LineChart, BarChart, XAxis, YAxis, CartesianGrid, Tooltip, Legend, Line, Bar } from 'recharts';
import { Camera } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

// Utility functions for data preparation
const preparePatternData = (pattern) => {
  const features = pattern.features.map((f, i) => ({
    name: `Feature ${i + 1}`,
    value: f
  }));

  const distribution = Object.entries(pattern.distribution || {}).map(([name, value]) => ({
    name,
    value
  }));

  return { features, distribution };
};

const prepareMetricsData = (metrics) => {
  return {
    processing_rate: metrics.processing_rate || 0,
    success_rate: metrics.success_rate || 0,
    active_patterns: metrics.active_patterns || 0,
    history: metrics.history || [],
  };
};

const prepareComparisonData = (patterns) => {
  return {
    similarity: patterns.map((p, i) => ({
      name: `Pattern ${i + 1}`,
      similarity: p.similarity || 0
    }))
  };
};

const prepareTopologyData = (topology) => {
  return {
    sites: topology.sites || [],
    connections: topology.connections || [],
    metrics: topology.metrics || {}
  };
};

// Pattern Visualization Component
export const PatternVisualization = ({ pattern, options = {} }) => {
  const [data, setData] = useState(null);

  useEffect(() => {
    if (pattern) {
      setData(preparePatternData(pattern));
    }
  }, [pattern]);

  if (!data) {
    return (
      <div className="flex items-center justify-center h-64">
        Loading pattern data...
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow-lg p-6">
      <div className="mb-4">
        <h3 className="text-xl font-semibold text-gray-800">Pattern Visualization</h3>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="bg-white rounded-lg p-4">
          <LineChart width={400} height={300} data={data.features}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="name" />
            <YAxis />
            <Tooltip />
            <Legend />
            <Line type="monotone" dataKey="value" stroke="#8884d8" />
          </LineChart>
        </div>

        <div className="bg-white rounded-lg p-4">
          <BarChart width={400} height={300} data={data.distribution}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="name" />
            <YAxis />
            <Tooltip />
            <Legend />
            <Bar dataKey="value" fill="#82ca9d" />
          </BarChart>
        </div>
      </div>

      <div className="mt-6">
        <h4 className="text-lg font-semibold mb-4">Pattern Details</h4>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="bg-gray-50 p-4 rounded-lg">
            <p className="text-sm text-gray-600">Type: {pattern.type}</p>
            <p className="text-sm text-gray-600">
              Confidence: {(pattern.confidence * 100).toFixed(1)}%
            </p>
          </div>
          <div className="bg-gray-50 p-4 rounded-lg">
            <p className="text-sm text-gray-600">
              Created: {new Date(pattern.created_at).toLocaleString()}
            </p>
            <p className="text-sm text-gray-600">
              Last Updated: {new Date(pattern.updated_at).toLocaleString()}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

// Metrics Dashboard Component
export const MetricsDashboard = ({ metrics, options = {} }) => {
  const [data, setData] = useState(null);

  useEffect(() => {
    if (metrics) {
      setData(prepareMetricsData(metrics));
    }
  }, [metrics]);

  if (!data) {
    return (
      <div className="flex items-center justify-center h-64">
        Loading metrics data...
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow-lg p-6">
      <h3 className="text-xl font-semibold text-gray-800 mb-6">Metrics Dashboard</h3>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div className="bg-blue-50 rounded-lg p-6">
          <h4 className="text-sm font-medium text-blue-600 mb-2">Processing Rate</h4>
          <p className="text-3xl font-bold text-blue-800">{data.processing_rate}/min</p>
        </div>

        <div className="bg-green-50 rounded-lg p-6">
          <h4 className="text-sm font-medium text-green-600 mb-2">Success Rate</h4>
          <p className="text-3xl font-bold text-green-800">{data.success_rate}%</p>
        </div>

        <div className="bg-yellow-50 rounded-lg p-6">
          <h4 className="text-sm font-medium text-yellow-600 mb-2">Active Patterns</h4>
          <p className="text-3xl font-bold text-yellow-800">{data.active_patterns}</p>
        </div>
      </div>

      <div className="bg-white rounded-lg p-4">
        <LineChart width={800} height={300} data={data.history}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey="timestamp" />
          <YAxis />
          <Tooltip />
          <Legend />
          <Line type="monotone" dataKey="processing_rate" stroke="#8884d8" name="Processing Rate" />
          <Line type="monotone" dataKey="success_rate" stroke="#82ca9d" name="Success Rate" />
        </LineChart>
      </div>
    </div>
  );
};

// Pattern Comparison Component
export const PatternComparison = ({ patterns, options = {} }) => {
  const [data, setData] = useState(null);

  useEffect(() => {
    if (patterns) {
      setData(prepareComparisonData(patterns));
    }
  }, [patterns]);

  if (!data) {
    return (
      <div className="flex items-center justify-center h-64">
        Loading comparison data...
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow-lg p-6">
      <h3 className="text-xl font-semibold text-gray-800 mb-6">Pattern Comparison</h3>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {patterns.map((pattern, index) => (
          <div key={index} className="border rounded-lg p-6">
            <h4 className="text-lg font-medium mb-4">Pattern {index + 1}</h4>
            <div className="space-y-2">
              <p className="text-sm text-gray-600">Type: {pattern.type}</p>
              <p className="text-sm text-gray-600">
                Confidence: {(pattern.confidence * 100).toFixed(1)}%
              </p>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-6">
        <h4 className="text-lg font-semibold mb-4">Similarity Analysis</h4>
        <div className="bg-white rounded-lg p-4">
          <BarChart width={800} height={300} data={data.similarity}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="name" />
            <YAxis />
            <Tooltip />
            <Legend />
            <Bar dataKey="similarity" fill="#8884d8" />
          </BarChart>
        </div>
      </div>
    </div>
  );
};

// Network Topology Component
export const NetworkTopology = ({ topology, options = {} }) => {
  const [data, setData] = useState(null);

  useEffect(() => {
    if (topology) {
      setData(prepareTopologyData(topology));
    }
  }, [topology]);

  if (!data) {
    return (
      <div className="flex items-center justify-center h-64">
        Loading topology data...
      </div>
    );
  }

  return (
    <div className="bg-white rounded-lg shadow-lg p-6">
      <h3 className="text-xl font-semibold text-gray-800 mb-6">Network Topology</h3>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {data.sites.map((site, index) => (
          <div key={index} className="border rounded-lg p-6">
            <div className="flex items-center justify-between mb-4">
              <h4 className="text-lg font-medium">Site {site.id}</h4>
              <span className={`px-2 py-1 rounded-full text-sm ${
                site.status === 'active' ? 'bg-green-100 text-green-800' :
                site.status === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                'bg-red-100 text-red-800'
              }`}>
                {site.status}
              </span>
            </div>
            <div className="space-y-2">
              <p className="text-sm text-gray-600">
                Active Patterns: {site.active_patterns}
              </p>
              <p className="text-sm text-gray-600">
                Processing Rate: {site.processing_rate}/min
              </p>
              <p className="text-sm text-gray-600">
                Last Sync: {new Date(site.last_sync).toLocaleString()}
              </p>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-6">
        <h4 className="text-lg font-semibold mb-4">Network Metrics</h4>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-gray-50 rounded-lg p-4">
            <h5 className="text-sm font-medium text-gray-600 mb-2">Total Sites</h5>
            <p className="text-2xl font-bold text-gray-800">{data.metrics.total_sites}</p>
          </div>
          <div className="bg-gray-50 rounded-lg p-4">
            <h5 className="text-sm font-medium text-gray-600 mb-2">Active Sites</h5>
            <p className="text-2xl font-bold text-gray-800">{data.metrics.active_sites}</p>
          </div>
          <div className="bg-gray-50 rounded-lg p-4">
            <h5 className="text-sm font-medium text-gray-600 mb-2">Network Health</h5>
            <p className="text-2xl font-bold text-gray-800">{data.metrics.health_score}%</p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default {
  PatternVisualization,
  MetricsDashboard,
  PatternComparison,
  NetworkTopology
};